<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class StoreTokenRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', $this->scopedExists('illimi_students')],
            'academic_year_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'code' => [
                'nullable',
                'string',
                'size:10',
                'regex:/^\d{8}[A-Z]{2}$/',
                Rule::unique('illimi_gradebook_tokens', 'code'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
