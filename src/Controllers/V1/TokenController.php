<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\GenerateTokenRequest;
use Illimi\Gradebook\Requests\StoreTokenRequest;
use Illimi\Gradebook\Requests\UpdateTokenRequest;
use Illimi\Gradebook\Resources\TokenCollection;
use Illimi\Gradebook\Resources\TokenResource;
use Illimi\Gradebook\Services\TokenService;

class TokenController extends BaseController
{
    public function __construct(
        protected TokenService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $filters = array_filter([
            'student_id' => $request->query('student_id'),
            'academic_class_id' => $request->query('academic_class_id'),
            'academic_year_id' => $request->query('academic_year_id'),
            'academic_term_id' => $request->query('academic_term_id'),
            'code' => $request->query('code'),
            'is_active' => $request->query('is_active'),
        ], fn ($value) => $value !== null && $value !== '');

        $tokens = $this->service->list($filters, $perPage);

        return $this->response->success(new TokenCollection($tokens), 'Tokens retrieved successfully');
    }

    public function store(StoreTokenRequest $request)
    {
        $token = $this->service->store($request->validated());
        event(new GradebookEntityChanged('token', 'created', (new TokenResource($token))->resolve()));

        return $this->response->success(new TokenResource($token), 'Token created successfully', 201);
    }

    public function show(string $id)
    {
        $token = $this->service->findById($id);

        if (! $token) {
            return $this->response->error('Token not found', 404);
        }

        return $this->response->success(new TokenResource($token), 'Token retrieved successfully');
    }

    public function update(UpdateTokenRequest $request, string $id)
    {
        $token = $this->service->update($id, $request->validated());

        if (! $token) {
            return $this->response->error('Token not found', 404);
        }

        event(new GradebookEntityChanged('token', 'updated', (new TokenResource($token))->resolve()));

        return $this->response->success(new TokenResource($token), 'Token updated successfully');
    }

    public function destroy(string $id)
    {
        $token = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (! $deleted) {
            return $this->response->error('Token not found', 404);
        }

        if ($token) {
            event(new GradebookEntityChanged('token', 'deleted', (new TokenResource($token))->resolve()));
        }

        return $this->response->success(null, 'Token deleted successfully');
    }

    public function generate(GenerateTokenRequest $request)
    {
        $tokens = $this->service->generate($request->validated());
        $organizationId = auth()->user()?->organization_id;

        event(new GradebookEntityChanged('token', 'bulk_generated', [
            'organization_id' => $organizationId,
            'generated_count' => $tokens->count(),
            'academic_class_id' => $request->validated('academic_class_id'),
            'academic_year_id' => $request->validated('academic_year_id'),
            'academic_term_id' => $request->validated('academic_term_id'),
        ]));

        return $this->response->success([
            'generated_count' => $tokens->count(),
            'tokens' => $tokens->map(fn ($token) => (new TokenResource($token))->resolve())->all(),
        ], 'Tokens generated successfully');
    }
}
