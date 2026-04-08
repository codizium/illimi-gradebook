<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentTemplateItem extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_template_items';

    protected $fillable = [
        'organization_id',
        'template_id',
        'label',
        'code',
        'component_type',
        'max_score',
        'weight',
        'position',
        'is_required',
        'affects_total',
        'settings',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'template_id' => 'string',
        'max_score' => 'decimal:2',
        'weight' => 'decimal:2',
        'position' => 'integer',
        'is_required' => 'boolean',
        'affects_total' => 'boolean',
        'settings' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'template_id');
    }
}
