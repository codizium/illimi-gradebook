<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class UpdateTokenRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tokenId = (string) $this->route('id');

        return [
            'student_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_students')],
            'academic_year_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'size:10',
                'regex:/^\d{8}[A-Z]{2}$/',
                Rule::unique('illimi_gradebook_tokens', 'code')->ignore($tokenId, 'id'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
