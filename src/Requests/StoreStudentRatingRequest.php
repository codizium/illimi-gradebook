<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class StoreStudentRatingRequest extends FormRequest
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
            'staff_id' => ['nullable', 'uuid', $this->scopedExists('illimi_staff')],
            'effective_assessment' => ['nullable', 'array'],
            'effective_assessment.*' => ['nullable', 'integer', 'min:1', 'max:5'],
            'psychomotor_assessment' => ['nullable', 'array'],
            'psychomotor_assessment.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
