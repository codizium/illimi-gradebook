<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class StoreReportRequest extends FormRequest
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
            'academic_class_id' => ['required', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'code' => ['nullable', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
