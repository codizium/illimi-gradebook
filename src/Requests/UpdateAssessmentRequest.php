<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class UpdateAssessmentRequest extends FormRequest
{
    use InteractsWithOrganizationValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_students')],
            'subject_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_subjects')],
            'academic_class_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['sometimes', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'template_id' => ['sometimes', 'nullable', 'uuid', $this->scopedExists('illimi_gradebook_templates')],
            'grade_scale_id' => ['nullable', 'uuid', $this->scopedExists('illimi_grade_scales')],
            'staff_id' => ['nullable', 'uuid', $this->scopedExists('illimi_staff')],
            'assignment1' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'assignment2' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'test1' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'test2' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'exams' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'graded' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items' => ['sometimes', 'array'],
            'items.*.template_item_id' => ['required_with:items', 'uuid', $this->scopedExists('illimi_gradebook_template_items')],
            'items.*.score' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    // public function message()
    // {
    //     return [

    //     ]
    // }
}
