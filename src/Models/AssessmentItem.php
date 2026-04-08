<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentItem extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_assessment_items';

    protected $fillable = [
        'organization_id',
        'assessment_id',
        'template_item_id',
        'score',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'assessment_id' => 'string',
        'template_item_id' => 'string',
        'score' => 'decimal:2',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplateItem::class, 'template_item_id');
    }
}
