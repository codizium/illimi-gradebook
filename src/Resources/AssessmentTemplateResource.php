<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject?->id,
                'name' => $this->subject?->name,
                'code' => $this->subject?->code,
            ]),
            'academic_class_id' => $this->academic_class_id,
            'academic_class' => $this->whenLoaded('academicClass', fn () => [
                'id' => $this->academicClass?->id,
                'name' => $this->academicClass?->name,
                'level' => $this->academicClass?->level,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear?->id,
                'name' => $this->academicYear?->name,
            ]),
            'academic_term_id' => $this->academic_term_id,
            'academic_term' => $this->whenLoaded('academicTerm', fn () => [
                'id' => $this->academicTerm?->id,
                'name' => $this->academicTerm?->name,
            ]),
            'is_default' => (bool) $this->is_default,
            'status' => $this->status,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'code' => $item->code,
                    'component_type' => $item->component_type,
                    'max_score' => $item->max_score !== null ? (float) $item->max_score : null,
                    'weight' => $item->weight !== null ? (float) $item->weight : null,
                    'position' => $item->position,
                    'is_required' => (bool) $item->is_required,
                    'affects_total' => (bool) $item->affects_total,
                    'settings' => $item->settings,
                ])->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
