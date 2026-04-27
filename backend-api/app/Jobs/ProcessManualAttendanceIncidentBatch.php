<?php

namespace App\Jobs;

use App\Services\ManualAttendanceIncidentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessManualAttendanceIncidentBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $batchId
    ) {
        $this->onQueue('default');
    }

    public function handle(ManualAttendanceIncidentService $incidentService): void
    {
        $incidentService->processBatch($this->batchId);
    }

    public function failed(\Throwable $exception): void
    {
        app(ManualAttendanceIncidentService::class)->markBatchFailed(
            $this->batchId,
            $exception->getMessage()
        );
    }
}
