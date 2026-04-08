<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\StoreAssessmentRequest;
use Illimi\Gradebook\Requests\UpdateAssessmentRequest;
use Illimi\Gradebook\Resources\AssessmentCollection;
use Illimi\Gradebook\Resources\AssessmentResource;
use Illimi\Gradebook\Services\AssessmentService;

class AssessmentController extends BaseController
{
    public function __construct(
        protected AssessmentService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $filters = array_filter([
            'student_id' => $request->query('student_id'),
            'subject_id' => $request->query('subject_id'),
            'academic_class_id' => $request->query('academic_class_id'),
            'academic_year_id' => $request->query('academic_year_id'),
            'academic_term_id' => $request->query('academic_term_id'),
            'grade_scale_id' => $request->query('grade_scale_id'),
            'staff_id' => $request->query('staff_id', $request->query('teacher_id')),
            'graded' => $request->query('graded'),
        ], fn ($value) => $value !== null && $value !== '');

        $assessments = $this->service->list($filters, $perPage);

        return $this->response->success(new AssessmentCollection($assessments), 'Assessments retrieved successfully');
    }

    public function store(StoreAssessmentRequest $request)
    {
        $assessment = $this->service->store($request->validated());
        event(new GradebookEntityChanged('assessment', 'saved', (new AssessmentResource($assessment))->resolve()));

        return $this->response->success(new AssessmentResource($assessment), 'Assessment saved successfully', 201);
    }

    public function show(string $id)
    {
        $assessment = $this->service->findById($id);

        if (!$assessment) {
            return $this->response->error('Assessment not found', 404);
        }

        return $this->response->success(new AssessmentResource($assessment), 'Assessment retrieved successfully');
    }

    public function update(UpdateAssessmentRequest $request, string $id)
    {
        $assessment = $this->service->update($id, $request->validated());

        if (!$assessment) {
            return $this->response->error('Assessment not found', 404);
        }

        event(new GradebookEntityChanged('assessment', 'updated', (new AssessmentResource($assessment))->resolve()));

        return $this->response->success(new AssessmentResource($assessment), 'Assessment updated successfully');
    }

    public function destroy(string $id)
    {
        $assessment = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->response->error('Assessment not found', 404);
        }

        if ($assessment) {
            event(new GradebookEntityChanged('assessment', 'deleted', (new AssessmentResource($assessment))->resolve()));
        }

        return $this->response->success(null, 'Assessment deleted successfully');
    }
}
