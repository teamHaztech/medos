<?php

namespace App\Console\Commands;

use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Queue\Jobs\EvaluateQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluateQueues extends Command
{
    protected $signature = 'medos:evaluate-queues';

    protected $description = 'Evaluate and reshuffle queues for all active doctors across hospitals';

    public function handle(): int
    {
        try {
            // Find all hospitals with active queue entries today
            $activeDoctors = QueueEntry::where('status', 'waiting')
                ->whereDate('created_at', today())
                ->select('hospital_id', 'doctor_id')
                ->distinct()
                ->get();

            $hospitalIds = $activeDoctors->pluck('hospital_id')->unique();
            $doctorCount = 0;

            foreach ($activeDoctors as $row) {
                EvaluateQueue::dispatch($row->hospital_id, $row->doctor_id);
                $doctorCount++;
            }

            $hospitalCount = $hospitalIds->count();

            $this->info("Dispatched queue evaluation for {$doctorCount} doctors across {$hospitalCount} hospitals");

            Log::info('[MedOS] Queue evaluation dispatched', [
                'doctors'   => $doctorCount,
                'hospitals' => $hospitalCount,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to evaluate queues: {$e->getMessage()}");
            Log::error('[MedOS] Queue evaluation command failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
