<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\Token;

class ReportService
{
    public function __construct(
        protected AssessmentService $assessmentService,
        protected StudentRatingService $ratingService
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

        $report = $this->baseQuery($payload['organization_id'] ?? null)->updateOrCreate([
            'organization_id' => $payload['organization_id'] ?? $this->organizationId(),
            'student_id' => $payload['student_id'],
            'academic_class_id' => $payload['academic_class_id'],
            'academic_year_id' => $payload['academic_year_id'],
            'academic_term_id' => $payload['academic_term_id'],
        ], $payload);

        return $this->findById($report->id) ?? $report->fresh();
    }

    public function update(string $id, array $data): ?Report
    {
        $report = $this->baseQuery()->find($id);

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
        $report = $this->baseQuery()->find($id);

        if (!$report) {
            return false;
        }

        return (bool) $report->delete();
    }

    public function generate(array $criteria): Report
    {
        $token = $this->resolveScopedToken($criteria, true);
        $criteria['code'] = $token->code;

        return $this->store($criteria);
    }

    protected function normalizePayload(array $data): array
    {
        $data['organization_id'] = $data['organization_id'] ?? $this->organizationId();

        $token = $this->resolveScopedToken($data, ! empty($data['student_id']) && ! empty($data['academic_year_id']) && ! empty($data['academic_term_id']) && ! empty($data['academic_class_id']));
        $tokenCode = $token?->code;

        if ((! array_key_exists('code', $data) || $data['code'] === '') && $tokenCode) {
            $data['code'] = $tokenCode;
        } elseif (! array_key_exists('code', $data) || $data['code'] === '') {
            $data['code'] = null;
        }

        if (!array_key_exists('payload', $data) || $data['payload'] === null) {
            $data['payload'] = $this->buildPayload($data);
        }

        return $data;
    }

    protected function buildPayload(array $criteria): array
    {
        $tokenCode = trim((string) ($criteria['code'] ?? ''));
        if ($tokenCode === '') {
            $tokenCode = $this->resolveScopedToken($criteria)?->code ?? '';
        }

        $ranking = $this->resolveStudentRanking($criteria);

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

        $templates = $assessments
            ->map(fn ($assessment) => $assessment->template)
            ->filter()
            ->unique(fn ($template) => $template->id)
            ->values();

        $resolvedTemplate = $templates->count() === 1 ? $templates->first() : null;
        $templateLabel = $resolvedTemplate?->name;

        if ($templates->count() > 1) {
            $templateLabel = 'Mixed Templates';
        }

        $templateItemsPayload = $resolvedTemplate?->items?->values()->map(function ($item, int $index) {
            return [
                'key' => (string) ($item->code ?: $item->id ?: 'component_' . ($index + 1)),
                'label' => $item->label,
                'code' => $item->code,
                'max_score' => $item->max_score,
                'component_type' => $item->component_type,
            ];
        })->all() ?? [];

        $assessmentItems = $assessments->map(function ($assessment) {
            $examTotal = (float) $assessment->items
                ->filter(fn ($item) => $item->templateItem?->component_type === 'exam')
                ->sum('score');
            $gradeCode = $assessment->gradeScale?->code ?? $assessment->getGrade();

            return [
                'subject_name' => $assessment->subject?->name,
                'subject_code' => $assessment->subject?->code,
                'template_name' => $assessment->template?->name,
                'template_code' => $assessment->template?->code,
                'teacher_name' => $assessment->staff?->full_name,
                'grade_scale' => [
                    'name' => $assessment->gradeScale?->name,
                    'code' => $assessment->gradeScale?->code,
                    'description' => $assessment->gradeScale?->description,
                ],
                'exams' => $examTotal,
                'exam_total' => $examTotal,
                'continuous_assessment_total' => (float) $assessment->continuous_assessment_total,
                'total_score' => (float) $assessment->total_score,
                'graded' => $gradeCode,
            ];
        })->values();

        $organization = $this->resolveOrganizationSnapshot($criteria['organization_id'] ?? null);
        $organizationModel = null;
        if (class_exists(\Codizium\Core\Models\Organization::class)) {
            $organizationModel = ($criteria['organization_id'] ?? null)
                ? \Codizium\Core\Models\Organization::query()->find($criteria['organization_id'])
                : (function_exists('organization') ? organization() : null);
        }
        $termName = $academicTerm?->name;

        return [
            'organization' => $organization,
            'presentation' => [
                'title' => $organizationModel?->getArtifactValue('report_card_title')
                    ?? $organizationModel?->getArtifactValue('school_report_title')
                    ?? ($termName ? "{$termName} Term Report Slip" : 'Report Slip'),
                'footer' => $organizationModel?->getArtifactValue('report_card_footer')
                    ?? $organizationModel?->getArtifactValue('school_report_footer')
                    ?? null,
            ],
            'student' => [
                'full_name' => $student?->full_name,
                'admission_number' => $student?->admission_number,
            ],
            'class' => [
                'name' => $academicClass?->name,
                'level' => $academicClass?->level,
                'section_name' => $academicClass?->section?->name,
            ],
            'academic_year' => [
                'name' => $academicYear?->name,
            ],
            'academic_term' => [
                'name' => $academicTerm?->name,
            ],
            'ids' => [
                'organization_id' => $criteria['organization_id'] ?? null,
                'student_id' => $student?->id,
                'academic_class_id' => $academicClass?->id,
                'academic_section_id' => $academicClass?->section?->id,
                'academic_year_id' => $academicYear?->id,
                'academic_term_id' => $academicTerm?->id,
                'template_id' => $resolvedTemplate?->id,
                'assessment_ids' => $assessments->pluck('id')->values()->all(),
                'template_item_ids' => $resolvedTemplate?->items?->pluck('id')->values()->all() ?? [],
            ],
            'template' => [
                'name' => $templateLabel,
                'code' => $resolvedTemplate?->code,
                'mixed' => $templates->count() > 1,
                'items' => $templateItemsPayload,
            ],
            'summary' => [
                'assessment_count' => $assessmentItems->count(),
                'continuous_assessment_total' => (float) $assessmentItems->sum('continuous_assessment_total'),
                'exam_total' => (float) $assessmentItems->sum('exam_total'),
                'overall_total' => (float) $assessmentItems->sum('total_score'),
                'average_score' => $assessmentItems->count() > 0
                    ? round((float) $assessmentItems->avg('total_score'), 2)
                    : 0.0,
                'position' => $ranking['position'],
                'position_in_class' => $ranking['position'],
                'class_position' => $ranking['position'],
                'rank' => $ranking['position'],
                'out_of' => $ranking['out_of'],
                'class_size' => $ranking['out_of'],
                'total_students' => $ranking['out_of'],
                'token' => $tokenCode !== '' ? $tokenCode : null,
                'remark' => $assessmentItems->pluck('remark')->filter()->map(fn ($remark) => trim((string) $remark))->first(),
            ],
            'token' => $tokenCode !== '' ? $tokenCode : null,
            'ratings' => $this->getRatings($criteria),
            'assessments' => $this->mapAssessmentsWithScores($assessments),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function resolveOrganizationSnapshot(?string $organizationId = null): array
    {
        $organization = function_exists('organization') ? organization() : null;

        if ((! $organization || ($organizationId && $organization->id !== $organizationId)) && $organizationId) {
            $organization = \Codizium\Core\Models\Organization::query()->find($organizationId);
        }

        return [
            'id' => $organization?->id,
            'name' => $organization?->getArtifactValue('text_portal_name', $organization?->name),
            'address' => $organization?->address,
            'email' => $organization?->email,
            'phone' => $organization?->phone,
            'logo' => $organization?->getArtifactValue('brand_logo') ?? $organization?->getAttachmentUrl('logo'),
            'icon' => $organization?->getArtifactValue('brand_icon') ?? $organization?->getAttachmentUrl('icon'),
        ];
    }

    protected function resolveStudentRanking(array $criteria): array
    {
        $studentId = $criteria['student_id'] ?? null;
        $academicClassId = $criteria['academic_class_id'] ?? null;
        $academicYearId = $criteria['academic_year_id'] ?? null;
        $academicTermId = $criteria['academic_term_id'] ?? null;
        $organizationId = $criteria['organization_id'] ?? $this->organizationId();

        if (! $studentId || ! $academicClassId || ! $academicYearId || ! $academicTermId) {
            return ['position' => null, 'out_of' => null];
        }

        $studentSummaries = Assessment::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('academic_class_id', $academicClassId)
            ->where('academic_year_id', $academicYearId)
            ->where('academic_term_id', $academicTermId)
            ->with('items.templateItem')
            ->get(['id', 'student_id', 'subject_id'])
            ->groupBy('student_id')
            ->map(function (Collection $studentAssessments, string $groupStudentId) {
                $overallTotal = round(
                    (float) $studentAssessments->sum(
                        fn (Assessment $assessment) => (float) $assessment->total_score,
                    ),
                    2,
                );
                $subjectCount = $studentAssessments->pluck('subject_id')->filter()->unique()->count();

                return [
                    'student_id' => $groupStudentId,
                    'overall_total' => $overallTotal,
                    'average_score' => $subjectCount > 0
                        ? round($overallTotal / $subjectCount, 2)
                        : 0.0,
                ];
            })
            ->sortBy([
                ['overall_total', 'desc'],
                ['student_id', 'asc'],
            ])
            ->values();

        if ($studentSummaries->isEmpty()) {
            return ['position' => null, 'out_of' => 0];
        }

        $position = 0;
        $rank = 0;
        $previousTotal = null;
        $rankings = [];

        foreach ($studentSummaries as $summary) {
            $position++;

            if ($previousTotal === null || abs((float) $summary['overall_total'] - (float) $previousTotal) > 0.00001) {
                $rank = $position;
                $previousTotal = (float) $summary['overall_total'];
            }

            $rankings[$summary['student_id']] = $rank;
        }

        return [
            'position' => $rankings[$studentId] ?? null,
            'out_of' => $studentSummaries->count(),
        ];
    }

    protected function resolveScopedToken(array $criteria, bool $required = false): ?Token
    {
        $organizationId = $criteria['organization_id'] ?? $this->organizationId();
        $studentId = $criteria['student_id'] ?? null;
        $academicClassId = $criteria['academic_class_id'] ?? null;
        $academicYearId = $criteria['academic_year_id'] ?? null;
        $academicTermId = $criteria['academic_term_id'] ?? null;

        if (! $studentId || ! $academicClassId || ! $academicYearId || ! $academicTermId) {
            return null;
        }

        $token = Token::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('student_id', $studentId)
            ->where('academic_class_id', $academicClassId)
            ->where('academic_year_id', $academicYearId)
            ->where('academic_term_id', $academicTermId)
            ->where('is_active', true)
            ->first();

        if (! $token && $required) {
            throw ValidationException::withMessages([
                'token' => ['No active result token exists for this student in the selected class, academic year, and term. Generate the token first before creating the report.'],
            ]);
        }

        return $token;
    }

    protected function mapAssessmentsWithScores($assessments): array
    {
        return $assessments->map(function ($assessment) {
            $examTotal = (float) $assessment->items
                ->filter(fn ($item) => $item->templateItem?->component_type === 'exam')
                ->sum('score');
            $gradeCode = $assessment->gradeScale?->code ?? $assessment->getGrade();
            $scores = $assessment->items->mapWithKeys(fn($item) => [
                $item->template_item_id => (float) $item->score
            ])->all();

            return [
                'subject_name' => $assessment->subject?->name,
                'template_name' => $assessment->template?->name,
                'template_code' => $assessment->template?->code,
                'components' => $assessment->items->map(function ($item) {
                    return [
                        'key' => (string) ($item->templateItem?->code ?: $item->templateItem?->id ?: $item->template_item_id),
                        'label' => $item->templateItem?->label,
                        'code' => $item->templateItem?->code,
                        'component_type' => $item->templateItem?->component_type,
                        'max_score' => $item->templateItem?->max_score,
                        'score' => (float) $item->score,
                    ];
                })->values()->all(),
                'scores' => $scores,
                'continuous_assessment_total' => (float) $assessment->continuous_assessment_total,
                'exams' => $examTotal,
                'total_score' => (float) $assessment->total_score,
                'graded' => $gradeCode,
                'grade_scale' => [
                    'code' => $gradeCode,
                    'description' => $assessment->gradeScale?->description,
                ]
            ];
        })->values()->all();
    }

    protected function getRatings(array $criteria): array
    {
        $rating = \Illimi\Gradebook\Models\StudentRating::query()
            ->where('student_id', $criteria['student_id'])
            ->where('academic_class_id', $criteria['academic_class_id'])
            ->where('academic_year_id', $criteria['academic_year_id'])
            ->where('academic_term_id', $criteria['academic_term_id'])
            ->first();

        if (!$rating) {
            return [
                'effective' => [],
                'psychomotor' => [],
            ];
        }

        $effective = [];
        foreach ($this->ratingService->effectiveItems() as $key => $label) {
            $val = $rating->effective_assessment[$key] ?? null;
            $effective[] = [
                'label' => $label,
                'value' => $val,
                'grade' => $this->ratingService->ratingLabel($val),
            ];
        }

        $psychomotor = [];
        foreach ($this->ratingService->psychomotorItems() as $key => $label) {
            $val = $rating->psychomotor_assessment[$key] ?? null;
            $psychomotor[] = [
                'label' => $label,
                'value' => $val,
                'grade' => $this->ratingService->ratingLabel($val),
            ];
        }

        return [
            'effective' => $effective,
            'psychomotor' => $psychomotor,
        ];
    }

    protected function query()
    {
        return $this->baseQuery()->with([
            'student',
            'academicClass',
            'academicYear',
            'academicTerm',
        ]);
    }

    protected function baseQuery(?string $organizationId = null)
    {
        return Report::query()
            ->when($organizationId ?? $this->organizationId(), fn ($query, $orgId) => $query->where('organization_id', $orgId));
    }

    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }
}
