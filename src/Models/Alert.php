<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alert extends BaseModel
{
    use BelongsToOrganization, HasCuid, SoftDeletes;

    protected $table = 'illimi_gradebook_alerts';

    protected $fillable = [
        'organization_id',
        'type',
        'severity',
        'context',
        'is_resolved',
        'detected_at',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'context' => 'array',
        'is_resolved' => 'boolean',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'resolution_note' => 'string',
    ];
}
