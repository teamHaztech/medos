<?php

namespace App\Modules\Queue\Jobs;

use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Queue\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $hospitalId;
    public ?string $doctorId;

    /**
     * Create a new job instance.
     *
     * @param string|null $hospitalId  Evaluate queues for a specific hospital, or all if null.
     * @param string|null $doctorId    Evaluate queue for a specific doctor, or all active doctors if null.
     */
    public function __construct(?string $hospitalId = null, ?string $doctorId = null)
    {
        $this->hospitalId = $hospitalId;
        $this->doctorId = $doctorId;
    }

    /**
     * Execute the job.
     */
    public function handle(HospitalContext $hospitalContext): void
    {
        if ($this->doctorId && $this->hospitalId) {
            // Evaluate a single doctor's queue
            $this->evaluateDoctor($hospitalContext, $this->hospitalId, $this->doctorId);
            return;
        }

        // Find all doctors with active queue entries today
        $query = QueueEntry::where('status', 'waiting')
            ->whereDate('created_at', today());

        if ($this->hospitalId) {
            $query->where('hospital_id', $this->hospitalId);
        }

        $activeDoctors = $query->select('hospital_id', 'doctor_id')
            ->distinct()
            ->get();

        foreach ($activeDoctors as $row) {
            $this->evaluateDoctor($hospitalContext, $row->hospital_id, $row->doctor_id);
        }

        // Handle no-show detection
        $this->detectNoShows();
    }

    /**
     * Evaluate and reshuffle a single doctor's queue.
     */
    protected function evaluateDoctor(
        HospitalContext $hospitalContext,
        string $hospitalId,
        string $doctorId,
    ): void {
        try {
            $hospitalContext->setHospitalId($hospitalId);

            $queueService = app(QueueService::class);
            $changes = $queueService->reshuffleQueue($doctorId);

            if (! empty($changes)) {
                Log::info("[MedOS] Queue reshuffled for doctor {$doctorId}", [
                    'hospital_id' => $hospitalId,
                    'changes'     => count($changes),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("[MedOS] Queue evaluation failed for doctor {$doctorId}", [
                'hospital_id' => $hospitalId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect patients who were called but did not show up within the timeout.
     */
    protected function detectNoShows(): void
    {
        $timeoutMinutes = config('medos.queue.no_show_timeout_minutes', 5);

        $noShows = QueueEntry::where('status', 'called')
            ->where('called_at', '<=', now()->subMinutes($timeoutMinutes))
            ->whereDate('created_at', today())
            ->get();

        foreach ($noShows as $entry) {
            $entry->skip();

            Log::info("[MedOS] Patient marked as no-show after timeout", [
                'queue_entry_id' => $entry->id,
                'patient_id'     => $entry->patient_id,
                'doctor_id'      => $entry->doctor_id,
                'called_at'      => $entry->called_at?->toIso8601String(),
            ]);
        }
    }
}
