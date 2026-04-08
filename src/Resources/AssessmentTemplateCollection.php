<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AssessmentTemplateCollection extends ResourceCollection
{
    public $collects = AssessmentTemplateResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'count' => $this->count(),
            ],
        ];
    }
}
