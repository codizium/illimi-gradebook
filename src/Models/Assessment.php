<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illimi\Academics\Scopes\TeacherScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Academics\Models\GradeScale;
use Illimi\Academics\Models\Subject;
use Illimi\Staff\Models\Staff;
use Illimi\Students\Models\Student;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_assessments';

    protected $fillable = [
        'organization_id',
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
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'student_id' => 'string',
        'subject_id' => 'string',
        'academic_class_id' => 'string',
        'academic_year_id' => 'string',
        'academic_term_id' => 'string',
        'template_id' => 'string',
        'grade_scale_id' => 'string',
        'staff_id' => 'string',
        'assignment1' => 'decimal:2',
        'assignment2' => 'decimal:2',
        'test1' => 'decimal:2',
        'test2' => 'decimal:2',
        'exams' => 'decimal:2',
    ];

    protected $appends = [
        'continuous_assessment_total',
        'total_score',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'template_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'assessment_id');
    }

    public function scopeTeacher(Builder $query, $user = null): Builder
    {
        return TeacherScope::apply($query, $user, 'subject.teachers');
    }

    public function getContinuousAssessmentTotalAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (float) $this->items
                ->filter(fn ($item) => $item->templateItem?->component_type === 'continuous_assessment')
                ->sum('score');
        }

        return (float) $this->assignment1
            + (float) $this->assignment2
            + (float) $this->test1
            + (float) $this->test2;
    }

    public function getTotalScoreAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (float) $this->items
                ->filter(fn ($item) => (bool) ($item->templateItem?->affects_total ?? true))
                ->sum('score');
        }

        return $this->continuous_assessment_total + (float) $this->exams;
    }
}
