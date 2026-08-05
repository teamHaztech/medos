<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apts = \App\Modules\Appointment\Models\Appointment::with(['patient', 'encounter'])
    ->orderBy('created_at', 'desc')
    ->take(3)
    ->get()
    ->toArray();

echo json_encode($apts, JSON_PRETTY_PRINT);
