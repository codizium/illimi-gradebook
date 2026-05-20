<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Codizium\Core\Traits\SecureResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\StoreAssessmentTemplateRequest;
use Illimi\Gradebook\Requests\UpdateAssessmentTemplateRequest;
use Illimi\Gradebook\Resources\AssessmentTemplateCollection;
use Illimi\Gradebook\Resources\AssessmentTemplateResource;
use Illimi\Gradebook\Services\AssessmentTemplateService;

class AssessmentTemplateController extends BaseController
{
    use SecureResponse;

    public function __construct(
        protected AssessmentTemplateService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $filters = array_filter([
            'subject_id' => $request->query('subject_id'),
            'academic_class_id' => $request->query('academic_class_id'),
            'academic_year_id' => $request->query('academic_year_id'),
            'academic_term_id' => $request->query('academic_term_id'),
            'status' => $request->query('status'),
            'is_default' => $request->query('is_default'),
        ], fn ($value) => $value !== null && $value !== '');

        $templates = $this->service->list($filters, $perPage);

        return $this->respondWithSecurity(new AssessmentTemplateCollection($templates), 'Assessment templates retrieved successfully', 200, $request);
    }

    public function store(StoreAssessmentTemplateRequest $request)
    {
        $template = $this->service->store($request->validated());
        event(new GradebookEntityChanged('assessment_template', 'created', (new AssessmentTemplateResource($template))->resolve()));

        return $this->respondWithSecurity(new AssessmentTemplateResource($template), 'Assessment template created successfully', 201, $request);
    }

    public function show(Request $request, string $id)
    {
        $template = $this->service->findById($id);

        if (!$template) {
            return $this->respondErrorWithSecurity('Assessment template not found', 404, [], $request);
        }

        return $this->respondWithSecurity(new AssessmentTemplateResource($template), 'Assessment template retrieved successfully', 200, $request);
    }

    public function update(UpdateAssessmentTemplateRequest $request, string $id)
    {
        $template = $this->service->update($id, $request->validated());

        if (!$template) {
            return $this->respondErrorWithSecurity('Assessment template not found', 404, [], $request);
        }

        event(new GradebookEntityChanged('assessment_template', 'updated', (new AssessmentTemplateResource($template))->resolve()));

        return $this->respondWithSecurity(new AssessmentTemplateResource($template), 'Assessment template updated successfully', 200, $request);
    }

    public function destroy(Request $request, string $id)
    {
        $template = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->respondErrorWithSecurity('Assessment template not found', 404, [], $request);
        }

        if ($template) {
            event(new GradebookEntityChanged('assessment_template', 'deleted', (new AssessmentTemplateResource($template))->resolve()));
        }

        return $this->respondWithSecurity(null, 'Assessment template deleted successfully', 200, $request);
    }
}
