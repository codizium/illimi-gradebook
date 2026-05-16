<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Academics\Models\GradeScale;
use Illimi\Academics\Models\Subject;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\AssessmentTemplate;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Models\Token;
use Illimi\Gradebook\Services\AssessmentTemplateService;
use Illimi\Gradebook\Services\StudentRatingService;
use Illimi\Staff\Models\Staff;
use Illimi\Students\Models\Student;

class GradebookContextController extends BaseController
{
    public function __construct(protected CoreJsonResponse $response)
    {
        parent::__construct();
    }

    protected function selectedAcademicYearId(Request $request): ?string
    {
        return $request->query('academic_year_id')
            ?: session('academic_context.academic_year_id');
    }

    protected function selectedAcademicTermId(Request $request): ?string
    {
        return $request->query('academic_term_id')
            ?: session('academic_context.academic_term_id');
    }

    protected function currentRoleContext(): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'admin';
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'super-admin', 'principal', 'organization-admin'])) {
            return 'admin';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('teacher')) {
            return 'teacher';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('student')) {
            return 'student';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('parent')) {
            return 'parent';
        }

        return 'admin';
    }

    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }

    protected function queryFor(string $modelClass): Builder
    {
        $query = $modelClass::query();
        $roleContext = $this->currentRoleContext();

        if ($roleContext === 'teacher' && method_exists($modelClass, 'scopeTeacher')) {
            $query->teacher();
        } elseif ($roleContext === 'student' && method_exists($modelClass, 'scopeStudent')) {
            $query->student();
        } elseif ($roleContext === 'parent' && method_exists($modelClass, 'scopeParent')) {
            $query->parent();
        }

        $query->when(
            $this->organizationId(),
            fn (Builder $q, string $organizationId) => $q->where('organization_id', $organizationId)
        );

        return $query;
    }

    protected function selectedAcademicPeriod(Request $request): array
    {
        $years = $this->queryFor(AcademicYear::class)->orderByDesc('start_date')->orderBy('name')->get();
        $terms = $this->queryFor(AcademicTerm::class)->orderBy('start_date')->orderBy('name')->get();

        $selectedAcademicYearId = $this->selectedAcademicYearId($request)
            ?: $years->firstWhere('status', 'active')?->id
            ?: $years->first()?->id;

        $termsForYear = $terms
            ->filter(fn (AcademicTerm $term) => ! $selectedAcademicYearId || $term->academic_year_id === $selectedAcademicYearId)
            ->values();

        $selectedAcademicTermId = $this->selectedAcademicTermId($request)
            ?: $termsForYear->firstWhere('status', 'active')?->id
            ?: $termsForYear->first()?->id;

        if (
            $selectedAcademicTermId &&
            ! $termsForYear->contains(fn (AcademicTerm $term) => $term->id === $selectedAcademicTermId)
        ) {
            $selectedAcademicTermId = $termsForYear->firstWhere('status', 'active')?->id ?: $termsForYear->first()?->id;
        }

        return [
            'academicYears' => $years,
            'academicTerms' => $terms,
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
        ];
    }

    public function dashboard(Request $request): JsonResponse
    {
        $counts = [
            'assessments' => $this->queryFor(Assessment::class)->count(),
            'templates' => $this->queryFor(AssessmentTemplate::class)->count(),
            'reports' => $this->queryFor(Report::class)->count(),
            'tokens' => $this->queryFor(Token::class)->count(),
        ];

        $period = $this->selectedAcademicPeriod($request);
        $activeYear = $period['academicYears']->firstWhere('id', $period['selectedAcademicYearId']);
        $activeTerm = $period['academicTerms']->firstWhere('id', $period['selectedAcademicTermId']);

        $analyticsRows = collect();
        if ($activeYear && $activeTerm) {
            $analyticsRows = $this->queryFor(Assessment::class)
                ->with(['subject', 'items.templateItem'])
                ->where('academic_year_id', $activeYear->id)
                ->where('academic_term_id', $activeTerm->id)
                ->get()
                ->groupBy('subject_id')
                ->map(function ($subjectAssessments) {
                    $first = $subjectAssessments->first();
                    $subjectName = $first?->subject?->name ?? 'Subject';
                    $studentCount = $subjectAssessments->pluck('student_id')->filter()->unique()->count();
                    $averageTotal = round($subjectAssessments->avg(fn ($assessment) => (float) $assessment->total_score), 2);
                    $passCount = $subjectAssessments->filter(fn ($assessment) => ((float) $assessment->total_score) >= 50)->count();
                    $passRate = $subjectAssessments->count() > 0
                        ? round(($passCount / $subjectAssessments->count()) * 100, 1)
                        : 0;

                    return [
                        'subject_id' => $first?->subject_id,
                        'subject_name' => $subjectName,
                        'students_assessed' => $studentCount,
                        'records' => $subjectAssessments->count(),
                        'average_score' => $averageTotal,
                        'pass_rate' => $passRate,
                    ];
                })
                ->sortByDesc('average_score')
                ->values();
        }

        $subjectPerformance = [
            'academic_year' => $activeYear?->name,
            'academic_term' => $activeTerm?->name,
            'series' => $analyticsRows,
        ];

        return $this->response->success([
            'counts' => $counts,
            'subjectPerformance' => $subjectPerformance,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $period['selectedAcademicYearId'],
            'selectedAcademicTermId' => $period['selectedAcademicTermId'],
        ], 'Gradebook dashboard context retrieved successfully.');
    }

    public function assessmentsIndex(Request $request): JsonResponse
    {
        $subjects = $this->queryFor(Subject::class)
            ->with(['classes' => function ($q) {
                $q->with('section')->withCount(['students' => function ($sq) {
                    $sq->where('status', Student::STATUS_ACTIVE);
                }]);
            }, 'teachers'])
            ->orderBy('name')
            ->get();

        $period = $this->selectedAcademicPeriod($request);
        $currentYear = $period['academicYears']->firstWhere('id', $period['selectedAcademicYearId']);
        $currentTerm = $period['academicTerms']->firstWhere('id', $period['selectedAcademicTermId']);

        $assessmentCounts = collect();
        if ($currentYear && $currentTerm) {
            $assessmentCounts = $this->queryFor(Assessment::class)
                ->where('academic_year_id', $currentYear->id)
                ->where('academic_term_id', $currentTerm->id)
                ->selectRaw('subject_id, academic_class_id, count(distinct student_id) as assessed_count')
                ->groupBy('subject_id', 'academic_class_id')
                ->get()
                ->keyBy(fn ($item) => $item->subject_id . '_' . $item->academic_class_id);
        }

        $subjectClassRows = $subjects->flatMap(function (Subject $subject) use ($assessmentCounts) {
            return $subject->classes->map(function (AcademicClass $class) use ($subject, $assessmentCounts) {
                $key = $subject->id . '_' . $class->id;
                $assessedCount = $assessmentCounts->get($key)?->assessed_count ?? 0;
                $totalStudents = $class->students_count ?? 0;

                return [
                    'subject' => $subject,
                    'class' => $class,
                    'teachers' => $subject->teachers,
                    'total_students' => $totalStudents,
                    'assessed_students' => $assessedCount,
                ];
            });
        })->values();

        return $this->response->success([
            'subjectClassRows' => $subjectClassRows,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $period['selectedAcademicYearId'],
            'selectedAcademicTermId' => $period['selectedAcademicTermId'],
        ], 'Gradebook assessment list context retrieved successfully.');
    }

    public function sheet(Request $request): JsonResponse
    {
        $subjectId = (string) $request->query('subject_id');
        $classId = (string) $request->query('class_id');
        if ($subjectId === '' || $classId === '') {
            return $this->response->error('subject_id and class_id are required.', 422);
        }

        $subjectModel = $this->queryFor(Subject::class)
            ->with(['teachers'])
            ->findOrFail($subjectId);

        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($classId);

        $period = $this->selectedAcademicPeriod($request);
        $gradeScales = $this->queryFor(GradeScale::class)
            ->orderByDesc('min_score')
            ->get();

        $students = $this->queryFor(Student::class)
            ->with('class')
            ->where('class_id', $classModel->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $selectedAcademicYearId = $period['selectedAcademicYearId'];
        $selectedAcademicTermId = $period['selectedAcademicTermId'];

        $assessments = collect();
        $resolvedTemplate = null;
        $templateItems = collect();
        $continuousAssessmentItems = collect();
        $nonContinuousItems = collect();
        $completionStats = [
            'total_students' => $students->count(),
            'assessed_students' => 0,
            'completion_percent' => 0,
        ];

        if ($selectedAcademicYearId && $selectedAcademicTermId) {
            $resolvedTemplate = app(AssessmentTemplateService::class)->resolveForContext([
                'subject_id' => $subjectModel->id,
                'academic_class_id' => $classModel->id,
                'academic_year_id' => $selectedAcademicYearId,
                'academic_term_id' => $selectedAcademicTermId,
                'status' => 'active',
            ]);

            $templateItems = $resolvedTemplate?->items?->values() ?? collect();
            $continuousAssessmentItems = $templateItems
                ->filter(fn ($item) => $item->component_type === 'continuous_assessment')
                ->values();
            $nonContinuousItems = $templateItems
                ->filter(fn ($item) => $item->component_type !== 'continuous_assessment')
                ->values();

            $assessments = $this->queryFor(Assessment::class)
                ->with(['gradeScale', 'items.templateItem', 'template.items'])
                ->where('subject_id', $subjectModel->id)
                ->where('academic_class_id', $classModel->id)
                ->where('academic_year_id', $selectedAcademicYearId)
                ->where('academic_term_id', $selectedAcademicTermId)
                ->get()
                ->keyBy('student_id');

            $assessedCount = $assessments
                ->filter(fn (Assessment $assessment) => $assessment->items?->isNotEmpty())
                ->count();

            $completionStats = [
                'total_students' => $students->count(),
                'assessed_students' => $assessedCount,
                'completion_percent' => $students->count() > 0
                    ? (int) round(($assessedCount / $students->count()) * 100)
                    : 0,
            ];
        }

        if ($templateItems->isEmpty()) {
            return $this->response->success([
                'subject' => $subjectModel,
                'class' => $classModel,
                'academicYears' => $period['academicYears'],
                'academicTerms' => $period['academicTerms'],
                'gradeScales' => $gradeScales,
                'termsForYear' => $period['termsForYear'],
                'selectedAcademicYearId' => $selectedAcademicYearId,
                'selectedAcademicTermId' => $selectedAcademicTermId,
                'resolvedTemplate' => null,
                'templateItems' => [],
                'continuousAssessmentItems' => [],
                'nonContinuousItems' => [],
                'gradebookRows' => [],
                'noTemplate' => true,
                'noTemplateMessage' => 'No template items found for this subject, class, academic year, and term.',
                'completionStats' => $completionStats,
            ], 'Gradebook sheet context retrieved successfully.');
        }

        $ratingService = app(StudentRatingService::class);
        $gradebookRows = $students->map(function (Student $student) use (
            $assessments,
            $resolvedTemplate,
            $templateItems,
            $continuousAssessmentItems,
            $nonContinuousItems,
            $ratingService
        ) {
            $assessment = $assessments->get($student->id);
            $activeTemplate = $assessment?->template ?? $resolvedTemplate;
            $rowTemplateItems = $activeTemplate?->items?->values() ?: $templateItems;

            $scoresByTemplateItemId = $assessment?->items
                ? $assessment->items->mapWithKeys(fn ($item) => [$item->template_item_id => (float) $item->score])
                : collect();

            $items = $rowTemplateItems->map(function ($item, $index) use ($scoresByTemplateItemId) {
                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'code' => $item->code,
                    'component_type' => $item->component_type,
                    'max_score' => $item->max_score,
                    'position' => $item->position ?? ($index + 1),
                    'affects_total' => (bool) ($item->affects_total ?? true),
                    'score' => $scoresByTemplateItemId->has($item->id) ? (string) $scoresByTemplateItemId->get($item->id) : '',
                ];
            })->values();

            $totalCa = collect($items)
                ->filter(fn ($item) => ($item['component_type'] ?? '') === 'continuous_assessment')
                ->sum(fn ($item) => (float) ($item['score'] ?? 0));

            $total = collect($items)
                ->filter(fn ($item) => (bool) ($item['affects_total'] ?? true))
                ->sum(fn ($item) => (float) ($item['score'] ?? 0));

            return [
                'student' => $student,
                'assessment' => $assessment,
                'template' => $activeTemplate,
                'items' => $items,
                'total_ca' => $totalCa,
                'total' => $total,
                'grade' => $assessment?->getGrade() ?? 'F',
                'remark' => $assessment?->gradeScale?->description,
            ];
        })->values();

        return $this->response->success([
            'subject' => $subjectModel,
            'class' => $classModel,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'gradeScales' => $gradeScales,
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'resolvedTemplate' => $resolvedTemplate,
            'templateItems' => $templateItems,
            'continuousAssessmentItems' => $continuousAssessmentItems,
            'nonContinuousItems' => $nonContinuousItems,
            'gradebookRows' => $gradebookRows,
            'completionStats' => $completionStats,
        ], 'Gradebook sheet context retrieved successfully.');
    }

    public function ratings(Request $request): JsonResponse
    {
        $classId = (string) $request->query('class_id');
        if ($classId === '') {
            return $this->response->error('class_id is required.', 422);
        }

        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($classId);

        $period = $this->selectedAcademicPeriod($request);
        $selectedAcademicYearId = $period['selectedAcademicYearId'];
        $selectedAcademicTermId = $period['selectedAcademicTermId'];

        $students = $this->queryFor(Student::class)
            ->where('class_id', $classModel->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $currentStaffId = optional(Staff::query()->where('user_id', auth()->id())->first())?->id;

        $ratingService = app(StudentRatingService::class);
        $context = [
            'academic_class_id' => $classModel->id,
            'academic_year_id' => $selectedAcademicYearId,
            'academic_term_id' => $selectedAcademicTermId,
        ];

        $ratings = $ratingService->forContext($context);
        $effectiveItems = $ratingService->effectiveItems();
        $psychomotorItems = $ratingService->psychomotorItems();

        return $this->response->success([
            'class' => $classModel,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'currentStaffId' => $currentStaffId,
            'students' => $students,
            'ratings' => $ratings,
            'effectiveItems' => $effectiveItems,
            'psychomotorItems' => $psychomotorItems,
        ], 'Gradebook rating context retrieved successfully.');
    }

    public function reports(Request $request): JsonResponse
    {
        // Lightweight context: use paginated API resources for main lists.
        $period = $this->selectedAcademicPeriod($request);
        $students = $this->queryFor(Student::class)
            ->where('status', Student::STATUS_ACTIVE)
            ->with('class.section')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $classes = $this->queryFor(AcademicClass::class)
            ->with('section')
            ->orderBy('name')
            ->get();

        return $this->response->success([
            'students' => $students,
            'classes' => $classes,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $period['selectedAcademicYearId'],
            'selectedAcademicTermId' => $period['selectedAcademicTermId'],
        ], 'Gradebook reports context retrieved successfully.');
    }

    public function tokens(Request $request): JsonResponse
    {
        $period = $this->selectedAcademicPeriod($request);
        $students = $this->queryFor(Student::class)
            ->where('status', Student::STATUS_ACTIVE)
            ->with('class')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $classes = $this->queryFor(AcademicClass::class)
            ->with('section')
            ->orderBy('name')
            ->get();

        return $this->response->success([
            'students' => $students,
            'classes' => $classes,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $period['selectedAcademicYearId'],
            'selectedAcademicTermId' => $period['selectedAcademicTermId'],
        ], 'Gradebook tokens context retrieved successfully.');
    }

    public function templates(Request $request): JsonResponse
    {
        $period = $this->selectedAcademicPeriod($request);

        $subjects = $this->queryFor(Subject::class)
            ->orderBy('name')
            ->get();

        $classes = $this->queryFor(AcademicClass::class)
            ->with('section')
            ->orderBy('name')
            ->get();

        $templates = $this->queryFor(AssessmentTemplate::class)
            ->with(['subject', 'academicClass.section', 'academicYear', 'academicTerm', 'items'])
            ->latest()
            ->get();

        return $this->response->success([
            'templates' => $templates,
            'subjects' => $subjects,
            'classes' => $classes,
            'academicYears' => $period['academicYears'],
            'academicTerms' => $period['academicTerms'],
            'termsForYear' => $period['termsForYear'],
            'selectedAcademicYearId' => $period['selectedAcademicYearId'],
            'selectedAcademicTermId' => $period['selectedAcademicTermId'],
        ], 'Gradebook templates context retrieved successfully.');
    }
}

