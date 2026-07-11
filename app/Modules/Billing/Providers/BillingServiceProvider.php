<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Observers\BillObserver;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Bill::observe(BillObserver::class);
    }
}
