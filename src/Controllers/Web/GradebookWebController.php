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

        // Apply role-based scopes in priority order.
        // TeacherScope is checked first because a Teacher who is also a Parent
        // should use Teacher-level visibility in the academic context.
        if (method_exists($modelClass, 'scopeTeacher')) {
            $query->teacher();
        } elseif (method_exists($modelClass, 'scopeStudent')) {
            $query->student();
        } elseif (method_exists($modelClass, 'scopeParent')) {
            $query->parent();
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


        }

        if ($templateItems->isEmpty()) {
            return back()->with('error', 'No template items found for this subject and class and year and term');
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
            'termsForYear' => $termsForYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedAcademicTermId' => $selectedAcademicTermId,
            'resolvedTemplate' => $resolvedTemplate,
            'templateItems' => $templateItems,
            'continuousAssessmentItems' => $continuousAssessmentItems,
            'nonContinuousItems' => $nonContinuousItems,
            'gradebookRows' => $gradebookRows,
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
