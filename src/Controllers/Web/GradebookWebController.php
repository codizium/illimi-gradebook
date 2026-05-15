<?php

namespace Illimi\Gradebook\Controllers\Web;

use Codizium\Core\Controllers\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Academics\Models\GradeScale;
use Illimi\Academics\Models\Subject;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\AssessmentTemplate;
use Illimi\Gradebook\Models\StudentRating;
use Illimi\Gradebook\Models\Token;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Services\AssessmentTemplateService;
use Illimi\Gradebook\Services\StudentRatingService;
use Illimi\Staff\Models\Staff;
use Illimi\Students\Models\Student;
use Illuminate\Http\Request;

class GradebookWebController extends BaseController
{
    protected function selectedAcademicYearId(): ?string
    {
        return request('academic_year_id')
            ?: session('academic_context.academic_year_id');
    }

    protected function selectedAcademicTermId(): ?string
    {
        return request('academic_term_id')
            ?: session('academic_context.academic_term_id');
    }

    protected function selectedAcademicPeriod(): array
    {
        $years = $this->queryFor(AcademicYear::class)->orderByDesc('start_date')->orderBy('name')->get();
        $terms = $this->queryFor(AcademicTerm::class)->orderBy('start_date')->orderBy('name')->get();

        $selectedAcademicYearId = $this->selectedAcademicYearId()
            ?: $years->firstWhere('status', 'active')?->id
            ?: $years->first()?->id;

        $termsForYear = $terms
            ->filter(fn (AcademicTerm $term) => ! $selectedAcademicYearId || $term->academic_year_id === $selectedAcademicYearId)
            ->values();

        $selectedAcademicTermId = $this->selectedAcademicTermId()
            ?: $termsForYear->firstWhere('status', 'active')?->id
            ?: $termsForYear->first()?->id;

        if ($selectedAcademicTermId && ! $termsForYear->contains(fn (AcademicTerm $term) => $term->id === $selectedAcademicTermId)) {
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
            fn(Builder $query, string $organizationId) => $query->where('organization_id', $organizationId)
        );

        return $query;
    }

    public function dashboard()
    {
        $counts = [
            'assessments' => $this->queryFor(Assessment::class)->count(),
            'templates' => $this->queryFor(AssessmentTemplate::class)->count(),
            'reports' => $this->queryFor(Report::class)->count(),
            'tokens' => $this->queryFor(Token::class)->count(),
        ];

        $period = $this->selectedAcademicPeriod();
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
                    $subjectName = $first?->subject?->name ?? 'Unknown Subject';

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

        return \Inertia\Inertia::render('Gradebook/Dashboard', compact('counts', 'subjectPerformance'));
    }

    public function assessments()
    {
        $subjects = $this->queryFor(Subject::class)
            ->with(['classes' => function($q) {
                $q->with('section')->withCount(['students' => function($sq) {
                    $sq->where('status', \Illimi\Students\Models\Student::STATUS_ACTIVE);
                }]);
            }, 'teachers'])
            ->orderBy('name')
            ->get();

        $period = $this->selectedAcademicPeriod();
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
                ->keyBy(function ($item) {
                    return $item->subject_id . '_' . $item->academic_class_id;
                });
        }

        $subjectClassRows = $subjects->flatMap(function (Subject $subject) use ($assessmentCounts) {
            return $subject->classes->map(function (AcademicClass $class) use ($subject, $assessmentCounts) {
                $key = $subject->id . '_' . $class->id;
                $assessedCount = $assessmentCounts->get($key)?->assessed_count ?? 0;
                $totalStudents = $class->students_count ?? 0;

                return (object) [
                    'subject' => $subject,
                    'class' => $class,
                    'teachers' => $subject->teachers,
                    'total_students' => $totalStudents,
                    'assessed_students' => $assessedCount,
                ];
            });
        })->values();

        return \Inertia\Inertia::render('Gradebook/Index', compact('subjectClassRows'));
    }

    #[Get('/gradebook/{subject}/{class}', name: 'gradebook.sheet')]
    public function show(string $subject, string $class)
    {
        return \Inertia\Inertia::render('Gradebook/Sheet', $this->loadGradebookContext($subject, $class));
    }

    public function effectiveAssessment(string $class)
    {
        return \Inertia\Inertia::render('Gradebook/EffectiveAssessment', $this->loadClassRatingContext($class));
    }

    public function psychomotorAssessment(string $class)
    {
        return \Inertia\Inertia::render('Gradebook/PsychomotorAssessment', $this->loadClassRatingContext($class));
    }

    private function loadGradebookContext(string $subject, string $class): array
    {
        $subjectModel = $this->queryFor(Subject::class)
            ->with(['teachers'])
            ->findOrFail($subject);

        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($class);

        $period = $this->selectedAcademicPeriod();
        $academicYears = $period['academicYears'];
        $academicTerms = $period['academicTerms'];
        $gradeScales = $this->queryFor(GradeScale::class)
            ->orderByDesc('min_score')
            ->get();
        $selectedAcademicYearId = $period['selectedAcademicYearId'];
        $selectedAcademicTermId = $period['selectedAcademicTermId'];
        $termsForYear = $period['termsForYear'];

        $students = $this->queryFor(Student::class)
            ->with('class')
            ->where('class_id', $classModel->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $assessments = collect();
        $resolvedTemplate = null;
        $templateItems = collect();
        $continuousAssessmentItems = collect();
        $nonContinuousItems = collect();
        $studentRatings = collect();
        $ratingService = app(StudentRatingService::class);
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
            return [
                'subject' => $subjectModel,
                'class' => $classModel,
                'academicYears' => $academicYears,
                'academicTerms' => $academicTerms,
                'gradeScales' => $gradeScales ?? collect(),
                'termsForYear' => $termsForYear,
                'selectedAcademicYearId' => $selectedAcademicYearId,
                'selectedAcademicTermId' => $selectedAcademicTermId,
                'resolvedTemplate' => null,
                'templateItems' => collect(),
                'continuousAssessmentItems' => collect(),
                'nonContinuousItems' => collect(),
                'gradebookRows' => collect(),
                'noTemplate' => true,
                'noTemplateMessage' => 'No template items found for this subject, class, academic year, and term.',
                'completionStats' => $completionStats,
            ];
        }

        $gradebookRows = $students->map(fn (Student $student) => $this->buildGradebookRow(
            $student,
            $assessments,
            $resolvedTemplate,
            $templateItems,
            $continuousAssessmentItems,
            $nonContinuousItems,
            $ratingService
        ));

        return [
            'subject' => $subjectModel,
            'class' => $classModel,
            'academicYears' => $academicYears,
            'academicTerms' => $academicTerms,
            'gradeScales' => $gradeScales,
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'resolvedTemplate' => $resolvedTemplate,
            'templateItems' => $templateItems,
            'continuousAssessmentItems' => $continuousAssessmentItems,
            'nonContinuousItems' => $nonContinuousItems,
            'gradebookRows' => $gradebookRows,
            'completionStats' => $completionStats,
        ];
    }

    private function loadClassRatingContext(string $class): array
    {
        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($class);

        $period = $this->selectedAcademicPeriod();
        $academicYears = $period['academicYears'];
        $academicTerms = $period['academicTerms'];
        $selectedAcademicYearId = $period['selectedAcademicYearId'];
        $selectedAcademicTermId = $period['selectedAcademicTermId'];
        $termsForYear = $period['termsForYear'];

        $students = $this->queryFor(Student::class)
            ->with('class')
            ->where('class_id', $classModel->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $ratings = collect();
        $effectiveItems = StudentRatingService::EFFECTIVE_ASSESSMENT_ITEMS;
        $psychomotorItems = StudentRatingService::PSYCHOMOTOR_ASSESSMENT_ITEMS;

        if ($selectedAcademicYearId && $selectedAcademicTermId) {
            $ratings = app(StudentRatingService::class)->ratingsForContext([
                'academic_class_id' => $classModel->id,
                'academic_year_id' => $selectedAcademicYearId,
                'academic_term_id' => $selectedAcademicTermId,
            ]);
        }

        
        $currentStaffId = $classModel->class_teacher_id ?: Staff::query()
            ->when($this->organizationId(), fn (Builder $query, string $organizationId) => $query->where('organization_id', $organizationId))
            ->where('user_id', auth()->id())
            ->value('id');

        return [
            'class' => $classModel,
            'academicYears' => $academicYears,
            'academicTerms' => $academicTerms,
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'currentStaffId' => $currentStaffId,
            'students' => $students,
            'ratings' => $ratings,
            'effectiveItems' => $effectiveItems,
            'psychomotorItems' => $psychomotorItems,
        ];
    }

    private function buildGradebookRow($student, $assessments, $resolvedTemplate, $templateItems, $continuousAssessmentItems, $nonContinuousItems, $ratingService)
    {
        $assessment = $assessments->get($student->id);

        $activeTemplate = $assessment?->template ?? $resolvedTemplate;

        $rowTemplateItems = $activeTemplate?->items?->values() ?: $templateItems;

        $rowContinuousItems = $rowTemplateItems
            ? $rowTemplateItems->filter(fn($item) => $item->component_type === 'continuous_assessment')->values()
            : $continuousAssessmentItems;

        $rowNonContinuousItems = $rowTemplateItems
            ? $rowTemplateItems->filter(fn($item) => $item->component_type !== 'continuous_assessment')->values()
            : $nonContinuousItems;

        $scoresByTemplateItemId = $assessment?->items
            ? $assessment->items->mapWithKeys(fn($item) => [$item->template_item_id => (float) $item->score])
            : collect();


        $items = $rowTemplateItems->map(function ($item, $index) use ($scoresByTemplateItemId) {
            return (object) [
                'id' => $item->id,
                'label' => $item->label,
                'code' => $item->code,
                'component_type' => $item->component_type,
                'max_score' => $item->max_score,
                'position' => $item->position ?? ($index + 1),
                'affects_total' => (bool) ($item->affects_total ?? true),
                'score' => $scoresByTemplateItemId->has($item->id) ? (string    ) $scoresByTemplateItemId->get($item->id) : '',
            ];
        })->values();

        
        $totalCa = $items
            ->filter(fn($item) => $item->component_type === 'continuous_assessment')
            ->sum(fn($item) => (float) $item->score);
        $total = $items
            ->filter(fn($item) => $item->affects_total)
            ->sum(fn($item) => (float) $item->score);

        return (object) [
            'student' => $student,
            'assessment' => $assessment,
            'template' => $activeTemplate,
            'items' => $items,
            'continuous_items' => $rowContinuousItems,
            'non_continuous_items' => $rowNonContinuousItems,
            'total_ca' => $totalCa,
            'total' => $total,
            'grade' => $assessment?->getGrade() ?? 'F',
            'remark' => $assessment?->gradeScale?->description,
        ];
    }

    public function reports()
    {
        $isTeacherContext = $this->currentRoleContext() === 'teacher';
        $teacherStudentIds = collect();
        $teacherClassIds = collect();

        if ($isTeacherContext) {
            $teacherAssessments = $this->queryFor(Assessment::class)
                ->select(['student_id', 'academic_class_id'])
                ->whereNotNull('student_id')
                ->whereNotNull('academic_class_id')
                ->get();

            $teacherStudentIds = $teacherAssessments->pluck('student_id')->filter()->unique()->values();
            $teacherClassIds = $teacherAssessments->pluck('academic_class_id')->filter()->unique()->values();
        }

        $reports = $this->queryFor(Report::class)
            ->with(['student', 'academicClass.section', 'academicYear', 'academicTerm'])
            ->when($isTeacherContext, function ($query) use ($teacherStudentIds, $teacherClassIds) {
                $query->where(function ($q) use ($teacherStudentIds, $teacherClassIds) {
                    if ($teacherStudentIds->isNotEmpty()) {
                        $q->whereIn('student_id', $teacherStudentIds->all());
                    }

                    if ($teacherClassIds->isNotEmpty()) {
                        $q->orWhereIn('academic_class_id', $teacherClassIds->all());
                    }

                    if ($teacherStudentIds->isEmpty() && $teacherClassIds->isEmpty()) {
                        $q->whereRaw('1 = 0');
                    }
                });
            })
            ->latest()
            ->limit(200)
            ->get();

        $students = Student::query()
            ->when(
                $this->organizationId(),
                fn(Builder $query, string $organizationId) => $query->where('organization_id', $organizationId)
            )
            ->where('status', Student::STATUS_ACTIVE)
            ->when($isTeacherContext, fn ($query) => $query->whereIn('id', $teacherStudentIds->all()))
            ->with('class.section')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $classes = AcademicClass::query()
            ->when(
                $this->organizationId(),
                fn(Builder $query, string $organizationId) => $query->where('organization_id', $organizationId)
            )
            ->when($isTeacherContext, fn ($query) => $query->whereIn('id', $teacherClassIds->all()))
            ->with('section')
            ->orderBy('name')
            ->get();

        $academicYears = $this->queryFor(AcademicYear::class)
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        $academicTerms = $this->queryFor(AcademicTerm::class)
            ->orderBy('start_date')
            ->orderBy('name')
            ->get();

        return \Inertia\Inertia::render('Gradebook/Reports', compact(
            'reports',
            'students',
            'classes',
            'academicYears',
            'academicTerms'
        ));
    }

    public function tokens(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 24), 100));

        $tokens = \Inertia\Inertia::scroll(fn () => $this->queryFor(Token::class)
            ->with(['student', 'academicClass', 'academicYear', 'academicTerm'])
            ->latest()
            ->paginate($perPage));

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

        $academicYears = $this->queryFor(AcademicYear::class)
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        $academicTerms = $this->queryFor(AcademicTerm::class)
            ->orderBy('start_date')
            ->orderBy('name')
            ->get();

        return \Inertia\Inertia::render('Gradebook/Tokens', compact(
            'tokens',
            'students',
            'classes',
            'academicYears',
            'academicTerms'
        ));
    }

    public function downloadToken(string $token)
    {
        $record = $this->queryFor(Token::class)
            ->with(['student', 'academicClass.section', 'academicYear', 'academicTerm'])
            ->where('id', $token)
            ->firstOrFail();

        $tokens = collect([$record]);

        $meta = [
            'scope' => 'token',
            'generated_at' => now()->toIso8601String(),
        ];

        $fileName = 'Token_' . ($record->student?->full_name ? str_replace(' ', '_', $record->student->full_name) : $record->student_id)
            . '_' . now()->format('Y-m-d_His');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('illimi-gradebook::pdf.tokens', [
            'tokens' => $tokens,
            'meta' => $meta,
        ]);

        return $pdf->download($fileName . '.pdf');
    }

    public function exportTokens(Request $request)
    {
        $scope = (string) $request->query('scope', 'all'); // all | class | student
        $studentId = $request->query('student_id');
        $classId = $request->query('academic_class_id');
        $yearId = $request->query('academic_year_id');
        $termId = $request->query('academic_term_id');

        if (!in_array($scope, ['all', 'class', 'student'], true)) {
            $scope = 'all';
        }

        if ($scope === 'student' && empty($studentId)) {
            return redirect()->back()->with('error', 'Select a student to export.');
        }
        if ($scope === 'class' && empty($classId)) {
            return redirect()->back()->with('error', 'Select a class to export.');
        }

        $query = $this->queryFor(Token::class)
            ->with(['student', 'academicClass.section', 'academicYear', 'academicTerm'])
            ->latest();

        if (!empty($yearId)) {
            $query->where('academic_year_id', $yearId);
        }
        if (!empty($termId)) {
            $query->where('academic_term_id', $termId);
        }

        if ($scope === 'student') {
            $query->where('student_id', $studentId);
        } elseif ($scope === 'class') {
            $query->where('academic_class_id', $classId);
        }

        $tokens = $query->get();
        if ($tokens->isEmpty()) {
            return redirect()->back()->with('error', 'No tokens found for this export.');
        }

        $meta = [
            'scope' => $scope,
            'generated_at' => now()->toIso8601String(),
        ];

        $fileName = 'Tokens_' . strtoupper($scope) . '_' . now()->format('Y-m-d_His');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('illimi-gradebook::pdf.tokens', [
            'tokens' => $tokens,
            'meta' => $meta,
        ]);

        return $pdf->download($fileName . '.pdf');
    }

    public function templates()
    {
        $templates = $this->queryFor(AssessmentTemplate::class)
            ->with(['subject', 'academicClass.section', 'academicYear', 'academicTerm', 'items'])
            ->latest()
            ->get();

        $subjects = $this->queryFor(Subject::class)
            ->orderBy('name')
            ->get();

        $classes = $this->queryFor(AcademicClass::class)
            ->with('section')
            ->orderBy('name')
            ->get();

        $academicYears = $this->queryFor(AcademicYear::class)
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        $academicTerms = $this->queryFor(AcademicTerm::class)
            ->orderBy('start_date')
            ->orderBy('name')
            ->get();

        return \Inertia\Inertia::render('Gradebook/Templates', compact(
            'templates',
            'subjects',
            'classes',
            'academicYears',
            'academicTerms'
        ));
    }
}
