<?php

namespace Illimi\Gradebook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illimi\Gradebook\Services\GradebookHealthService;

class RunGradebookIntegrityChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected ?string $organizationId = null
    ) {
    }

    public function handle(GradebookHealthService $healthService): void
    {
        $healthService->runChecks($this->organizationId);
    }
}
