<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class UpdateAssessmentTemplateRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'subject_id' => ['sometimes', 'nullable', 'uuid', $this->scopedExists('illimi_subjects')],
            'academic_class_id' => ['sometimes', 'nullable', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['sometimes', 'nullable', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['sometimes', 'nullable', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.label' => ['required_with:items', 'string', 'max:255'],
            'items.*.code' => ['required_with:items', 'string', 'max:100'],
            'items.*.component_type' => ['required_with:items', 'string', 'in:continuous_assessment,exam,other'],
            'items.*.max_score' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.position' => ['nullable', 'integer', 'min:0'],
            'items.*.is_required' => ['nullable', 'boolean'],
            'items.*.affects_total' => ['nullable', 'boolean'],
            'items.*.settings' => ['nullable', 'array'],
        ];
    }
}
