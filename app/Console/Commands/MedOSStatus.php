<?php

namespace App\Console\Commands;

use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Models\Hospital;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MedOSStatus extends Command
{
    protected $signature = 'medos:status';

    protected $description = 'Display current MedOS system status overview';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== MedOS Hospital OS - System Status ===');
        $this->newLine();

        try {
            $this->showHospitalStatus();
            $this->showPatientStatus();
            $this->showQueueStatus();
            $this->showJobQueueStatus();
            $this->showAIConversationStatus();
            $this->showRecentErrors();
        } catch (\Throwable $e) {
            $this->error("Error fetching status: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showHospitalStatus(): void
    {
        $totalHospitals = Hospital::count();
        $activeHospitals = Hospital::where('is_active', true)->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Hospitals', $totalHospitals],
                ['Active Hospitals', $activeHospitals],
            ],
        );
    }

    private function showPatientStatus(): void
    {
        $todayPatients = Encounter::whereDate('created_at', today())->count();
        $totalPatients = Patient::count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Patients (all time)', $totalPatients],
                ['Patients Today (encounters)', $todayPatients],
            ],
        );
    }

    private function showQueueStatus(): void
    {
        $activeQueues = QueueEntry::where('status', 'waiting')
            ->whereDate('created_at', today())
            ->select('hospital_id', 'doctor_id', DB::raw('COUNT(*) as depth'))
            ->groupBy('hospital_id', 'doctor_id')
            ->get();

        if ($activeQueues->isEmpty()) {
            $this->info('No active queues at the moment.');
            $this->newLine();

            return;
        }

        $rows = $activeQueues->map(function ($queue) {
            return [
                $queue->hospital_id,
                $queue->doctor_id,
                $queue->depth,
            ];
        })->toArray();

        $this->info('Active Queues:');
        $this->table(['Hospital ID', 'Doctor ID', 'Queue Depth'], $rows);
    }

    private function showJobQueueStatus(): void
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $this->table(
                ['Queue Metric', 'Value'],
                [
                    ['Pending Jobs', $pendingJobs],
                    ['Failed Jobs', $failedJobs],
                ],
            );
        } catch (\Throwable) {
            $this->warn('Job queue tables not available (run migrations first).');
            $this->newLine();
        }
    }

    private function showAIConversationStatus(): void
    {
        try {
            $activeConversations = DB::table('conversations')
                ->where('status', 'active')
                ->count();

            $this->table(
                ['AI Metric', 'Value'],
                [
                    ['Active AI Conversations', $activeConversations],
                ],
            );
        } catch (\Throwable) {
            $this->warn('Conversations table not available.');
            $this->newLine();
        }
    }

    private function showRecentErrors(): void
    {
        $logFile = storage_path('logs/laravel.log');

        if (! File::exists($logFile)) {
            $this->info('No log file found.');

            return;
        }

        // Read last portion of the log file to find errors
        $logContent = File::size($logFile) > 50000
            ? File::get($logFile, false, null, -50000)
            : File::get($logFile);

        // Extract error lines
        $lines = explode("\n", $logContent);
        $errors = [];

        foreach ($lines as $line) {
            if (str_contains($line, '.ERROR:') || str_contains($line, '.CRITICAL:')) {
                $errors[] = $line;
            }
        }

        $recentErrors = array_slice($errors, -5);

        if (empty($recentErrors)) {
            $this->info('No recent errors in logs.');

            return;
        }

        $this->warn('Last 5 Errors:');
        $rows = [];

        foreach ($recentErrors as $i => $error) {
            // Truncate long error messages for table display
            $truncated = mb_strlen($error) > 120
                ? mb_substr($error, 0, 120) . '...'
                : $error;
            $rows[] = [$i + 1, $truncated];
        }

        $this->table(['#', 'Error'], $rows);
    }
}
