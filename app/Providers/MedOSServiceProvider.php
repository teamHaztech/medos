<?php

namespace App\Providers;

use App\Modules\Admin\Middleware\CheckRole;
use App\Modules\Admin\Middleware\ResolveHospital;
use App\Modules\AIReceptionist\Events\EmergencyDetected;
use App\Modules\Core\Events\EncounterCompleted;
use App\Modules\Core\Listeners\EmergencyAlertListener;
use App\Modules\Core\Listeners\EncounterCompletedListener;
use App\Modules\Core\Listeners\PatientCalledNotifier;
use App\Modules\Core\Listeners\QueueUpdateNotifier;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Queue\Events\PatientCalled;
use App\Modules\Queue\Events\QueueUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MedOSServiceProvider extends ServiceProvider
{
    /**
     * All module service bindings.
     */
    protected array $moduleBindings = [
        // Core
        \App\Modules\Core\Services\HospitalContext::class,

        // Add module service bindings here as they are built:
        // \App\Modules\WhatsApp\Services\WhatsAppService::class,
        // \App\Modules\AIReceptionist\Services\AIReceptionistService::class,
        // \App\Modules\Triage\Services\TriageEngine::class,
        // \App\Modules\Queue\Services\QueueManager::class,
        // \App\Modules\Appointment\Services\AppointmentService::class,
        // \App\Modules\Insurance\Services\InsuranceService::class,
        // \App\Modules\Billing\Services\BillingService::class,
        // \App\Modules\Patient\Services\PatientService::class,
        // \App\Modules\DoctorAssist\Services\DoctorAssistService::class,
        // \App\Modules\Analytics\Services\AnalyticsService::class,
    ];

    /**
     * Event listener mappings.
     */
    protected array $listen = [
        EncounterCompleted::class => [
            EncounterCompletedListener::class,
        ],
        EmergencyDetected::class => [
            EmergencyAlertListener::class,
        ],
        QueueUpdated::class => [
            QueueUpdateNotifier::class,
        ],
        PatientCalled::class => [
            PatientCalledNotifier::class,
        ],
        // \App\Modules\Patient\Events\PatientRegistered::class => [
        //     \App\Modules\Insurance\Listeners\VerifyInsurance::class,
        //     \App\Modules\Engagement\Listeners\SendWelcomeMessage::class,
        // ],
        // \App\Modules\Appointment\Events\AppointmentBooked::class => [
        //     \App\Modules\Queue\Listeners\AddToQueue::class,
        //     \App\Modules\Engagement\Listeners\SendAppointmentConfirmation::class,
        // ],
        // \App\Modules\Triage\Events\TriageCompleted::class => [
        //     \App\Modules\Queue\Listeners\UpdateQueuePriority::class,
        // ],
    ];

    /**
     * Artisan commands provided by MedOS modules.
     */
    protected array $commands = [
        \App\Console\Commands\EvaluateQueues::class,
        \App\Console\Commands\SendReminders::class,
        \App\Console\Commands\ProcessFollowUps::class,
        \App\Console\Commands\ReEngagePatients::class,
        \App\Console\Commands\MedOSStatus::class,
    ];

    /**
     * Register MedOS services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/medos.php',
            'medos'
        );

        // Register HospitalContext as a singleton
        $this->app->singleton(HospitalContext::class, function ($app) {
            return new HospitalContext();
        });

        // Register middleware aliases
        $this->app->bind('resolve.hospital', ResolveHospital::class);
        $this->app->bind('role', CheckRole::class);
    }

    /**
     * Bootstrap MedOS services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerEventListeners();
        $this->registerCommands();
        $this->publishConfig();
    }

    /**
     * Register API v1 routes.
     */
    protected function registerRoutes(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(base_path('routes/api_v1.php'));
    }

    /**
     * Register event listeners from all modules.
     */
    protected function registerEventListeners(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    /**
     * Publish MedOS config for customization.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/medos.php' => config_path('medos.php'),
        ], 'medos-config');
    }
}
