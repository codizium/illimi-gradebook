<?php

namespace Illimi\Gradebook\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illimi\Gradebook\Requests\Concerns\InteractsWithOrganizationValidation;

class StoreAssessmentRequest extends FormRequest
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
            'subject_id' => ['required', 'uuid', $this->scopedExists('illimi_subjects')],
            'academic_class_id' => ['required', 'uuid', $this->scopedExists('illimi_classes')],
            'academic_year_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_years')],
            'academic_term_id' => ['required', 'uuid', $this->scopedExists('illimi_academic_terms')],
            'template_id' => ['nullable', 'uuid', $this->scopedExists('illimi_gradebook_templates')],
            'grade_scale_id' => ['nullable', 'uuid', $this->scopedExists('illimi_grade_scales')],
            'staff_id' => ['nullable', 'uuid', $this->scopedExists('illimi_staff')],
            'assignment1' => ['nullable', 'numeric', 'min:0'],
            'assignment2' => ['nullable', 'numeric', 'min:0'],
            'test1' => ['nullable', 'numeric', 'min:0'],
            'test2' => ['nullable', 'numeric', 'min:0'],
            'exams' => ['nullable', 'numeric', 'min:0'],
            'graded' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
            'items.*.template_item_id' => ['required_with:items', 'uuid', $this->scopedExists('illimi_gradebook_template_items')],
            'items.*.score' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
