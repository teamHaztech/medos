<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\MedOSServiceProvider;

return [
    AppServiceProvider::class,
    MedOSServiceProvider::class,
    BillingServiceProvider::class,
];
