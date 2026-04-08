<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class StoreAssessmentTemplateRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'uuid', $this->scopedExists('illimi_subjects')],
            'academic_class_id' => ['nullable', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['nullable', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['nullable', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.code' => ['required', 'string', 'max:100'],
            'items.*.component_type' => ['required', 'string', 'in:continuous_assessment,exam,other'],
            'items.*.max_score' => ['required', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.position' => ['nullable', 'integer', 'min:0'],
            'items.*.is_required' => ['nullable', 'boolean'],
            'items.*.affects_total' => ['nullable', 'boolean'],
            'items.*.settings' => ['nullable', 'array'],
        ];
    }
}
