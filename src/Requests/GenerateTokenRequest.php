<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class GenerateTokenRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_class_id' => ['required', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['required', 'uuid', $this->scopedExists('illimi_students')],
            'replace_existing' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
