<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illimi\Gradebook\Models\Report;

class ReportService
{
    public function __construct(
        protected AssessmentService $assessmentService
    ) {
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        foreach ([
            'student_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
        ] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?Report
    {
        return $this->query()->find($id);
    }

    public function store(array $data): Report
    {
        $payload = $this->normalizePayload($data);

        $report = Report::query()->updateOrCreate([
            'student_id' => $payload['student_id'],
            'academic_class_id' => $payload['academic_class_id'],
            'academic_year_id' => $payload['academic_year_id'],
            'academic_term_id' => $payload['academic_term_id'],
        ], $payload);

        return $this->findById($report->id) ?? $report->fresh();
    }

    public function update(string $id, array $data): ?Report
    {
        $report = Report::find($id);

        if (!$report) {
            return null;
        }

        $payload = $this->normalizePayload(array_merge($report->only([
            'student_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
            'code',
            'payload',
        ]), $data));

        $report->update($payload);

        return $this->findById($report->id);
    }

    public function delete(string $id): bool
    {
        $report = Report::find($id);

        if (!$report) {
            return false;
        }

        return (bool) $report->delete();
    }

    public function generate(array $criteria): Report
    {
        return $this->store($criteria);
    }

    protected function normalizePayload(array $data): array
    {
        if (!array_key_exists('code', $data) || $data['code'] === '') {
            $data['code'] = null;
        }

        if (!array_key_exists('payload', $data) || $data['payload'] === null) {
            $data['payload'] = $this->buildPayload($data);
        }

        return $data;
    }

    protected function buildPayload(array $criteria): array
    {
        $assessments = $this->assessmentService->assessmentsForReport($criteria);
        $student = optional($assessments->first())->student;
        $academicClass = optional($assessments->first())->academicClass;
        $academicYear = optional($assessments->first())->academicYear;
        $academicTerm = optional($assessments->first())->academicTerm;

        if (!$student && !empty($criteria['student_id'])) {
            $student = \Illimi\Students\Models\Student::query()->find($criteria['student_id']);
        }

        if (!$academicClass && !empty($criteria['academic_class_id'])) {
            $academicClass = \Illimi\Academics\Models\AcademicClass::query()->find($criteria['academic_class_id']);
        }

        if (!$academicYear && !empty($criteria['academic_year_id'])) {
            $academicYear = \Illimi\Academics\Models\AcademicYear::query()->find($criteria['academic_year_id']);
        }

        if (!$academicTerm && !empty($criteria['academic_term_id'])) {
            $academicTerm = \Illimi\Academics\Models\AcademicTerm::query()->find($criteria['academic_term_id']);
        }

        $assessmentItems = $assessments->map(function ($assessment) {
            return [
                'id' => $assessment->id,
                'subject' => [
                    'id' => $assessment->subject?->id,
                    'name' => $assessment->subject?->name,
                    'code' => $assessment->subject?->code,
                ],
                'teacher' => [
                    'id' => $assessment->staff?->id,
                    'full_name' => $assessment->staff?->full_name,
                    'email' => $assessment->staff?->email,
                ],
                'grade_scale' => [
                    'id' => $assessment->gradeScale?->id,
                    'name' => $assessment->gradeScale?->name,
                    'code' => $assessment->gradeScale?->code,
                ],
                'assignment1' => (float) $assessment->assignment1,
                'assignment2' => (float) $assessment->assignment2,
                'test1' => (float) $assessment->test1,
                'test2' => (float) $assessment->test2,
                'exams' => (float) $assessment->exams,
                'continuous_assessment_total' => (float) $assessment->continuous_assessment_total,
                'total_score' => (float) $assessment->total_score,
                'graded' => $assessment->graded,
            ];
        })->values();

        return [
            'student' => [
                'id' => $student?->id,
                'full_name' => $student?->full_name,
                'admission_number' => $student?->admission_number,
                'email' => $student?->email,
            ],
            'class' => [
                'id' => $academicClass?->id,
                'name' => $academicClass?->name,
                'level' => $academicClass?->level,
            ],
            'academic_year' => [
                'id' => $academicYear?->id,
                'name' => $academicYear?->name,
                'slug' => $academicYear?->slug,
            ],
            'academic_term' => [
                'id' => $academicTerm?->id,
                'name' => $academicTerm?->name,
                'slug' => $academicTerm?->slug,
            ],
            'summary' => [
                'assessment_count' => $assessmentItems->count(),
                'continuous_assessment_total' => (float) $assessmentItems->sum('continuous_assessment_total'),
                'exam_total' => (float) $assessmentItems->sum('exams'),
                'overall_total' => (float) $assessmentItems->sum('total_score'),
                'average_score' => $assessmentItems->count() > 0
                    ? round((float) $assessmentItems->avg('total_score'), 2)
                    : 0.0,
            ],
            'assessments' => $assessmentItems->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function query()
    {
        return Report::query()->with([
            'student',
            'academicClass',
            'academicYear',
            'academicTerm',
        ]);
    }
}
