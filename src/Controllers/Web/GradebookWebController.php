<?php

namespace Illimi\Gradebook\Controllers\Web;

use Codizium\Core\Controllers\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Academics\Models\Subject;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\AssessmentTemplate;
use Illimi\Gradebook\Models\StudentRating;
use Illimi\Gradebook\Models\Token;
use Illimi\Gradebook\Services\AssessmentTemplateService;
use Illimi\Gradebook\Services\StudentRatingService;
use Illimi\Staff\Models\Staff;
use Illimi\Students\Models\Student;

class GradebookWebController extends BaseController
{
    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }

    protected function queryFor(string $modelClass): Builder
    {
        $query = $modelClass::query();

        if (method_exists($modelClass, 'scopeTeacher')) {
            $query->teacher();
        }

        $query->when(
            $this->organizationId(),
            fn(Builder $query, string $organizationId) => $query->where('organization_id', $organizationId)
        );

        return $query;
    }

    public function index()
    {
        $subjects = $this->queryFor(Subject::class)
            ->with(['classes.section', 'teachers'])
            ->orderBy('name')
            ->get();

        $subjectClassRows = $subjects->flatMap(function (Subject $subject) {
            return $subject->classes->map(function (AcademicClass $class) use ($subject) {
                return (object) [
                    'subject' => $subject,
                    'class' => $class,
                    'teachers' => $subject->teachers,
                ];
            });
        })->values();

        return view('illimi-gradebook::pages.index', compact('subjectClassRows'));
    }

    #[Get('/gradebook/{subject}/{class}', name: 'gradebook.sheet')]
    public function show(string $subject, string $class)
    {
        return view('illimi-gradebook::pages.sheet', $this->loadGradebookContext($subject, $class));
    }

    public function effectiveAssessment(string $class)
    {
        return view('illimi-gradebook::pages.effective-assessment', $this->loadClassRatingContext($class));
    }

    public function psychomotorAssessment(string $class)
    {
        return view('illimi-gradebook::pages.psychomotor-assessment', $this->loadClassRatingContext($class));
    }

    private function loadGradebookContext(string $subject, string $class): array
    {
        $subjectModel = $this->queryFor(Subject::class)
            ->with(['teachers'])
            ->findOrFail($subject);

        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($class);

        $academicYears = $this->queryFor(AcademicYear::class)->orderByDesc('start_date')->orderBy('name')->get();
        $academicTerms = $this->queryFor(AcademicTerm::class)->orderBy('start_date')->orderBy('name')->get();

        $selectedAcademicYearId = request('academic_year_id') ?: $academicYears->first()?->id;

        $termsForYear = $academicTerms
            ->filter(fn (AcademicTerm $term) => ! $selectedAcademicYearId || $term->academic_year_id === $selectedAcademicYearId)
            ->values();

        $selectedAcademicTermId = request('academic_term_id') ?: $termsForYear->first()?->id ?: $academicTerms->first()?->id;

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

            $studentRatings = $this->queryFor(StudentRating::class)
                ->where('academic_class_id', $classModel->id)
                ->where('academic_year_id', $selectedAcademicYearId)
                ->where('academic_term_id', $selectedAcademicTermId)
                ->get()
                ->keyBy('student_id');
        }

        if ($templateItems->isEmpty()) {
            $templateItems = collect([
                (object) ['id' => 'assignment1', 'label' => 'Assignment 1', 'code' => 'A1', 'component_type' => 'continuous_assessment', 'max_score' => null, 'position' => 1, 'affects_total' => true],
                (object) ['id' => 'assignment2', 'label' => 'Assignment 2', 'code' => 'A2', 'component_type' => 'continuous_assessment', 'max_score' => null, 'position' => 2, 'affects_total' => true],
                (object) ['id' => 'test1', 'label' => 'Test 1', 'code' => 'T1', 'component_type' => 'continuous_assessment', 'max_score' => null, 'position' => 3, 'affects_total' => true],
                (object) ['id' => 'test2', 'label' => 'Test 2', 'code' => 'T2', 'component_type' => 'continuous_assessment', 'max_score' => null, 'position' => 4, 'affects_total' => true],
                (object) ['id' => 'exams', 'label' => 'Exams', 'code' => 'EXAM', 'component_type' => 'exam', 'max_score' => null, 'position' => 5, 'affects_total' => true],
            ]);
            $continuousAssessmentItems = $templateItems
                ->filter(fn ($item) => $item->component_type === 'continuous_assessment')
                ->values();
            $nonContinuousItems = $templateItems
                ->filter(fn ($item) => $item->component_type !== 'continuous_assessment')
                ->values();
        }

        $gradebookRows = $students->map(fn (Student $student) => $this->buildGradebookRow(
            $student,
            $assessments,
            $studentRatings,
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
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'resolvedTemplate' => $resolvedTemplate,
            'templateItems' => $templateItems,
            'continuousAssessmentItems' => $continuousAssessmentItems,
            'nonContinuousItems' => $nonContinuousItems,
            'gradebookRows' => $gradebookRows,
            'effectiveAssessmentItems' => $ratingService->effectiveItems(),
            'psychomotorAssessmentItems' => $ratingService->psychomotorItems(),
        ];
    }

    private function loadClassRatingContext(string $class): array
    {
        $classModel = $this->queryFor(AcademicClass::class)
            ->with(['section', 'classTeacher'])
            ->findOrFail($class);

        $academicYears = $this->queryFor(AcademicYear::class)->orderByDesc('start_date')->orderBy('name')->get();
        $academicTerms = $this->queryFor(AcademicTerm::class)->orderBy('start_date')->orderBy('name')->get();

        $selectedAcademicYearId = request('academic_year_id') ?: $academicYears->first()?->id;

        $termsForYear = $academicTerms
            ->filter(fn (AcademicTerm $term) => ! $selectedAcademicYearId || $term->academic_year_id === $selectedAcademicYearId)
            ->values();

        $selectedAcademicTermId = request('academic_term_id') ?: $termsForYear->first()?->id ?: $academicTerms->first()?->id;

        $students = $this->queryFor(Student::class)
            ->with('class')
            ->where('class_id', $classModel->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $studentRatings = collect();
        $ratingService = app(StudentRatingService::class);

        if ($selectedAcademicYearId && $selectedAcademicTermId) {
            $studentRatings = $this->queryFor(StudentRating::class)
                ->where('academic_class_id', $classModel->id)
                ->where('academic_year_id', $selectedAcademicYearId)
                ->where('academic_term_id', $selectedAcademicTermId)
                ->get()
                ->keyBy('student_id');
        }

        $currentStaffId = $classModel->class_teacher_id ?: Staff::query()
            ->when($this->organizationId(), fn (Builder $query, string $organizationId) => $query->where('organization_id', $organizationId))
            ->where('user_id', auth()->id())
            ->value('id');

        $ratingRows = $students->map(function (Student $student) use ($studentRatings, $ratingService) {
            $studentRating = $studentRatings->get($student->id);

            return (object) [
                'student' => $student,
                'student_rating' => $studentRating,
                'effective_assessment' => collect($ratingService->effectiveItems())->map(function ($label, $key) use ($studentRating, $ratingService) {
                    $value = $studentRating?->effective_assessment[$key] ?? null;

                    return (object) [
                        'key' => $key,
                        'label' => $label,
                        'value' => $value,
                        'rating' => $ratingService->ratingLabel($value),
                    ];
                })->values(),
                'psychomotor_assessment' => collect($ratingService->psychomotorItems())->map(function ($label, $key) use ($studentRating, $ratingService) {
                    $value = $studentRating?->psychomotor_assessment[$key] ?? null;

                    return (object) [
                        'key' => $key,
                        'label' => $label,
                        'value' => $value,
                        'rating' => $ratingService->ratingLabel($value),
                    ];
                })->values(),
            ];
        });

        return [
            'class' => $classModel,
            'academicYears' => $academicYears,
            'academicTerms' => $academicTerms,
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'ratingRows' => $ratingRows,
            'effectiveAssessmentItems' => $ratingService->effectiveItems(),
            'psychomotorAssessmentItems' => $ratingService->psychomotorItems(),
            'currentStaffId' => $currentStaffId,
        ];
    }

    private function buildGradebookRow($student, $assessments, $studentRatings, $resolvedTemplate, $templateItems, $continuousAssessmentItems, $nonContinuousItems, $ratingService)
    {
        $assessment = $assessments->get($student->id);
        $studentRating = $studentRatings->get($student->id);
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

        if ($rowTemplateItems->isEmpty()) {
            $rowTemplateItems = $templateItems;
            $rowContinuousItems = $templateItems->filter(fn($item) => $item->component_type === 'continuous_assessment')->values();
            $rowNonContinuousItems = $templateItems->filter(fn($item) => $item->component_type !== 'continuous_assessment')->values();
            $scoresByTemplateItemId = collect([
                'assignment1' => (float) ($assessment?->assignment1 ?? 0),
                'assignment2' => (float) ($assessment?->assignment2 ?? 0),
                'test1' => (float) ($assessment?->test1 ?? 0),
                'test2' => (float) ($assessment?->test2 ?? 0),
                'exams' => (float) ($assessment?->exams ?? 0),
            ]);
        }

        $items = $rowTemplateItems->map(function ($item, $index) use ($scoresByTemplateItemId) {
            return (object) [
                'id' => $item->id,
                'label' => $item->label,
                'code' => $item->code,
                'component_type' => $item->component_type,
                'max_score' => $item->max_score,
                'position' => $item->position ?? ($index + 1),
                'affects_total' => (bool) ($item->affects_total ?? true),
                'score' => (float) ($scoresByTemplateItemId->get($item->id) ?? 0),
            ];
        })->values();

        $totalCa = $items
            ->filter(fn($item) => $item->component_type === 'continuous_assessment')
            ->sum('score');
        $total = $items
            ->filter(fn($item) => $item->affects_total)
            ->sum('score');

        return (object) [
            'student' => $student,
            'assessment' => $assessment,
            'student_rating' => $studentRating,
            'template' => $activeTemplate,
            'items' => $items,
            'continuous_items' => $rowContinuousItems,
            'non_continuous_items' => $rowNonContinuousItems,
            'total_ca' => $totalCa,
            'total' => $total,
            'grade' => $assessment?->graded,
            'remark' => $assessment?->gradeScale?->description,
            'effective_assessment' => collect($ratingService->effectiveItems())->map(function ($label, $key) use ($studentRating, $ratingService) {
                $value = $studentRating?->effective_assessment[$key] ?? null;

                return (object) [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'rating' => $ratingService->ratingLabel($value),
                ];
            })->values(),
            'psychomotor_assessment' => collect($ratingService->psychomotorItems())->map(function ($label, $key) use ($studentRating, $ratingService) {
                $value = $studentRating?->psychomotor_assessment[$key] ?? null;

                return (object) [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'rating' => $ratingService->ratingLabel($value),
                ];
            })->values(),
        ];
    }

    public function reports()
    {
        return view('illimi-gradebook::pages.reports');
    }

    public function tokens()
    {
        $tokens = $this->queryFor(Token::class)
            ->with(['student', 'academicClass', 'academicYear', 'academicTerm'])
            ->latest()
            ->get();

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

        return view('illimi-gradebook::pages.tokens', compact(
            'tokens',
            'students',
            'classes',
            'academicYears',
            'academicTerms'
        ));
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

        return view('illimi-gradebook::pages.templates', compact(
            'templates',
            'subjects',
            'classes',
            'academicYears',
            'academicTerms'
        ));
    }
}
