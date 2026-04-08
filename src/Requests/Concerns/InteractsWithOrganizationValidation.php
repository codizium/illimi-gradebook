<?php

namespace Illimi\Gradebook\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait InteractsWithOrganizationValidation
{
    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }

    protected function scopedExists(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        if ($organizationId = $this->organizationId()) {
            $rule->where(fn ($query) => $query->where('organization_id', $organizationId));
        }

        return $rule;
    }
}
