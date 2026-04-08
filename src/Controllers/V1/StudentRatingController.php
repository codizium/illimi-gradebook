<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illimi\Gradebook\Requests\StoreStudentRatingRequest;
use Illimi\Gradebook\Resources\StudentRatingResource;
use Illimi\Gradebook\Services\StudentRatingService;

class StudentRatingController extends BaseController
{
    public function __construct(
        protected StudentRatingService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function store(StoreStudentRatingRequest $request)
    {
        $payload = $request->validated();
        $payload['organization_id'] = optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;

        $rating = $this->service->store($payload);

        return $this->response->success(new StudentRatingResource($rating), 'Student rating saved successfully', 201);
    }
}
