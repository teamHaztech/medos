<?php
/**
 * One-time importer: add doctors (staff + login accounts) to Healthway Hospital.
 * Access: https://medos.haztech.cloud/import-healthway-doctors.php?key=haztech2026
 * DELETE this file after running it.
 *
 * Idempotent: re-running skips doctors whose email already exists.
 * Default login password for every doctor: password123
 */

if (($_GET['key'] ?? '') !== 'haztech2026') { die('Unauthorized'); }

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

header('Content-Type: text/plain; charset=utf-8');

// --- Doctors from healthwayhospitals.com/doctor-profile (page 1) ---
// department is a placeholder — edit later in Super Admin > Hospital > Staff.
$doctors = [
    ['name' => 'Dr. Amit Subhash Kalangutkar', 'department' => 'General Medicine'],
    ['name' => 'Dr. Praveen Satish',           'department' => 'General Medicine'],
    ['name' => 'Dr. Reuben De Souza',          'department' => 'General Medicine'],
    ['name' => 'Dr. Hemchandra Maenkar',       'department' => 'General Medicine'],
    ['name' => 'Dr. Suraj Rane',               'department' => 'General Medicine'],
    ['name' => 'Dr. Farook Sayed',             'department' => 'General Medicine'],
    ['name' => 'Dr. Aparna Joshi',             'department' => 'Gynecology'],
    ['name' => 'Dr. Chandrakant Sharma',       'department' => 'General Medicine'],
    ['name' => 'Dr. Janitta Shamkant Kundaikar','department' => 'Pediatrics'],
    ['name' => 'Dr. Prathibha B. Naik',        'department' => 'Gynecology'],
];

$emailDomain = 'healthway.medos.local';
$defaultPassword = 'password123';

// --- Find Healthway Hospital ---
$hospital = Hospital::where('name', 'like', '%Healthway%')->first();
if (!$hospital) {
    echo "ERROR: No hospital found matching 'Healthway'. Create it first in Super Admin.\n";
    exit;
}
echo "Hospital: {$hospital->name} (id " . substr($hospital->id, 0, 8) . ", slug {$hospital->slug})\n";
echo str_repeat('-', 60) . "\n";

$emailFromName = function (string $name) use ($emailDomain): string {
    $base = strtolower($name);
    $base = preg_replace('/^dr\.?\s+/', '', $base);          // drop "Dr."
    $base = preg_replace('/[^a-z0-9]+/', '.', $base);         // spaces -> dots
    $base = trim($base, '.');
    return $base . '@' . $emailDomain;
};

$created = 0; $skipped = 0;

foreach ($doctors as $doc) {
    $finalEmail = $emailFromName($doc['name']);

    // Idempotent: if this login already exists, skip (safe to re-run).
    if (User::where('email', $finalEmail)->exists()) {
        echo "SKIP  {$doc['name']} — {$finalEmail} already exists\n";
        $skipped++;
        continue;
    }

    $staffId = Str::uuid()->toString();
    $userId  = Str::uuid()->toString();

    User::create([
        'id'          => $userId,
        'name'        => $doc['name'],
        'email'       => $finalEmail,
        'password'    => Hash::make($defaultPassword),
        'hospital_id' => $hospital->id,
        'role'        => 'doctor',
        'is_active'   => true,
    ]);

    Staff::withoutGlobalScopes()->create([
        'id'          => $staffId,
        'hospital_id' => $hospital->id,
        'user_id'     => $userId,
        'name'        => $doc['name'],
        'email'       => $finalEmail,
        'role'        => 'doctor',
        'department'  => $doc['department'],
        'consultation_duration_default' => 15,
        'is_active'   => true,
    ]);

    if (Schema::hasTable('staff_hospital')) {
        DB::table('staff_hospital')->insert([
            'id'          => Str::uuid()->toString(),
            'staff_id'    => $staffId,
            'hospital_id' => $hospital->id,
            'role'        => 'doctor',
            'department'  => $doc['department'],
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    echo "ADD   {$doc['name']}  ({$doc['department']})  login: {$finalEmail} / {$defaultPassword}\n";
    $created++;
}

echo str_repeat('-', 60) . "\n";
echo "Done. Created {$created}, skipped {$skipped}.\n";
echo "All logins use password: {$defaultPassword}\n";
echo "\n*** DELETE this file (public/import-healthway-doctors.php) now. ***\n";
