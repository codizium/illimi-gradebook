<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illimi\Academics\Models\AcademicClass;
use Illimi\Academics\Models\AcademicTerm;
use Illimi\Academics\Models\AcademicYear;
use Illimi\Academics\Models\Subject;

class AssessmentTemplate extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_templates';

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'subject_id',
        'academic_class_id',
        'academic_year_id',
        'academic_term_id',
        'is_default',
        'status',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'subject_id' => 'string',
        'academic_class_id' => 'string',
        'academic_year_id' => 'string',
        'academic_term_id' => 'string',
        'is_default' => 'boolean',
    ];

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

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentTemplateItem::class, 'template_id')->orderBy('position');
    }
}
