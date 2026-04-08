<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illimi\Academics\Models\GradeScale;
use Illimi\Academics\Models\Subject;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\AssessmentTemplate;

class AssessmentService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        foreach ([
            'student_id',
            'subject_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
            'template_id',
            'grade_scale_id',
            'staff_id',
        ] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('graded', $filters) && $filters['graded'] !== null && $filters['graded'] !== '') {
            $query->where('graded', $filters['graded']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?Assessment
    {
        return $this->query()->find($id);
    }

    public function store(array $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->normalizePayload($data);
            $this->assertTeacherCanAccessSubject($payload['subject_id']);

            $assessment = Assessment::query()->teacher()->firstOrNew([
                'student_id' => $payload['student_id'],
                'subject_id' => $payload['subject_id'],
                'academic_class_id' => $payload['academic_class_id'],
                'academic_year_id' => $payload['academic_year_id'],
                'academic_term_id' => $payload['academic_term_id'],
            ]);

            $assessment->fill($payload);
            $assessment->save();

            if (!empty($data['items'])) {
                $this->syncAssessmentItems($assessment, $data['items']);
            }

            return $this->findById($assessment->id) ?? $assessment->fresh();
        });
    }

    public function update(string $id, array $data): ?Assessment
    {
        $assessment = Assessment::query()->teacher()->find($id);

        if (!$assessment) {
            return null;
        }

        $payload = $this->normalizePayload(array_merge($assessment->only([
            'student_id',
            'subject_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
            'template_id',
            'grade_scale_id',
            'staff_id',
            'assignment1',
            'assignment2',
            'test1',
            'test2',
            'exams',
            'graded',
        ]), $data));
        $this->assertTeacherCanAccessSubject($payload['subject_id']);

        return DB::transaction(function () use ($assessment, $data, $payload) {
            $assessment->update($payload);

            if (array_key_exists('items', $data)) {
                $this->syncAssessmentItems($assessment, $data['items'] ?? []);
            }

            return $this->findById($assessment->id);
        });
    }

    public function delete(string $id): bool
    {
        $assessment = Assessment::query()->teacher()->find($id);

        if (!$assessment) {
            return false;
        }

        return (bool) $assessment->delete();
    }

    public function assessmentsForReport(array $criteria)
    {
        return $this->query()
            ->where('student_id', $criteria['student_id'])
            ->where('academic_class_id', $criteria['academic_class_id'])
            ->where('academic_year_id', $criteria['academic_year_id'])
            ->where('academic_term_id', $criteria['academic_term_id'])
            ->orderBy('subject_id')
            ->get();
    }

    protected function normalizePayload(array $data): array
    {
        if (!empty($data['template_id']) && !empty($data['items'])) {
            $template = AssessmentTemplate::query()
                ->with('items')
                ->find($data['template_id']);

            if ($template) {
                $validationErrors = [];
                $itemScores = collect($data['items'])
                    ->keyBy('template_item_id');

                $componentTotals = [
                    'continuous_assessment' => 0.0,
                    'exam' => 0.0,
                ];
                $overallTotal = 0.0;

                foreach ($template->items as $templateItem) {
                    $score = (float) ($itemScores->get($templateItem->id)['score'] ?? 0);

                    if ($score < 0) {
                        $validationErrors["items.{$templateItem->id}.score"] = [
                            "{$templateItem->label} score cannot be less than 0.",
                        ];
                    }

                    if ($templateItem->max_score !== null && $score > (float) $templateItem->max_score) {
                        $validationErrors["items.{$templateItem->id}.score"] = [
                            "{$templateItem->label} score cannot be greater than {$templateItem->max_score}.",
                        ];
                    }

                    if ($templateItem->component_type === 'continuous_assessment') {
                        $componentTotals['continuous_assessment'] += $score;
                    }

                    if ($templateItem->component_type === 'exam') {
                        $componentTotals['exam'] += $score;
                    }

                    if ($templateItem->affects_total) {
                        $overallTotal += $score;
                    }
                }

                if ($validationErrors !== []) {
                    throw ValidationException::withMessages($validationErrors);
                }

                $data['assignment1'] = $componentTotals['continuous_assessment'];
                $data['assignment2'] = 0.0;
                $data['test1'] = 0.0;
                $data['test2'] = 0.0;
                $data['exams'] = $componentTotals['exam'];
                $data['_computed_total_score'] = $overallTotal;
            }
        }

        foreach (['assignment1', 'assignment2', 'test1', 'test2', 'exams'] as $field) {
            $data[$field] = isset($data[$field]) ? (float) $data[$field] : 0.0;
        }

        $totalScore = $data['_computed_total_score'] ?? ($data['assignment1']
            + $data['assignment2']
            + $data['test1']
            + $data['test2']
            + $data['exams']);

        $gradeScale = null;

        if (!empty($data['grade_scale_id'])) {
            $gradeScale = GradeScale::query()->find($data['grade_scale_id']);
        }

        if (!$gradeScale) {
            $gradeScale = GradeScale::query()
                ->where('min_score', '<=', $totalScore)
                ->where('max_score', '>=', $totalScore)
                ->orderBy('min_score', 'desc')
                ->first();
        }

        if ($gradeScale) {
            $data['grade_scale_id'] = $gradeScale->id;
            $data['graded'] = $data['graded'] ?? $gradeScale->code ?? $gradeScale->name;
        }

        return $data;
    }

    protected function syncAssessmentItems(Assessment $assessment, array $items): void
    {
        $assessment->items()->delete();

        foreach ($items as $item) {
            $assessment->items()->create([
                'organization_id' => $assessment->organization_id,
                'template_item_id' => $item['template_item_id'],
                'score' => $item['score'],
            ]);
        }
    }

    protected function query()
    {
        return Assessment::query()->teacher()->with([
            'student',
            'subject',
            'academicClass',
            'academicYear',
            'academicTerm',
            'template',
            'gradeScale',
            'staff',
            'items.templateItem',
        ]);
    }

    protected function assertTeacherCanAccessSubject(string $subjectId): void
    {
        $subject = Subject::query()->teacher()->find($subjectId);

        if (! $subject) {
            throw new AuthorizationException('You are not allowed to manage assessments for this subject.');
        }
    }
}
