<?php

namespace Illimi\Gradebook\Models;

use Codizium\Core\Models\BaseModel;
use Codizium\Core\Traits\BelongsToOrganization;
use Codizium\Core\Traits\HasCuid;

class HealthCheck extends BaseModel
{
    use BelongsToOrganization, HasCuid;

    protected $table = 'illimi_gradebook_health_checks';

    protected $fillable = [
        'organization_id',
        'check_name',
        'status',
        'meta',
        'checked_at',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'meta' => 'array',
        'checked_at' => 'datetime',
    ];
}
