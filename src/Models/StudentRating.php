<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Staff\Models\Staff;
use Illimi\Students\Models\Student;

class StudentRating extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_student_ratings';

    protected $fillable = [
        'organization_id',
        'student_id',
        'academic_class_id',
        'academic_year_id',
        'academic_term_id',
        'staff_id',
        'effective_assessment',
        'psychomotor_assessment',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'student_id' => 'string',
        'academic_class_id' => 'string',
        'academic_year_id' => 'string',
        'academic_term_id' => 'string',
        'staff_id' => 'string',
        'effective_assessment' => 'array',
        'psychomotor_assessment' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
