<?php
/**
 * MedOS Deployment Script for Shared Hosting
 * Access: https://medos.haztech.cloud/deploy.php?key=haztech2026
 * DELETE this file after successful deployment!
 */

if (($_GET['key'] ?? '') !== 'haztech2026') { die('Unauthorized'); }

// Boot Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "<html><head><title>MedOS Deploy</title>
<style>body{font-family:monospace;padding:40px;background:#0f172a;color:#e2e8f0;max-width:800px;margin:0 auto;}
h1{color:#60a5fa;}pre{background:#1e293b;padding:20px;border-radius:8px;overflow:auto;margin:16px 0;}
.ok{color:#4ade80;}.err{color:#f87171;}.info{color:#fbbf24;}</style></head><body>";
echo "<h1>MedOS Deployment</h1>";
echo "<p style='color:#94a3b8;margin:0 0 8px;'>Version: <strong style='color:#60a5fa;'>v2.5.0</strong> &middot; Built: <strong>2026-05-28 02:00 IST</strong> &middot; Git: <code>" . trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?: 'unknown') . "</code></p>";
echo "<pre>";

$root = dirname(__DIR__);

// 1. Check .env
echo "<span class='info'>1. Checking .env...</span>\n";
if (file_exists($root . '/.env')) {
    echo "<span class='ok'>✓ .env exists</span>\n";
} else {
    echo "<span class='err'>✗ .env missing! Create it via File Manager.</span>\n";
    echo "</pre></body></html>";
    exit;
}

// 2. Check database file
echo "\n<span class='info'>2. Checking database...</span>\n";
$dbPath = env('DB_DATABASE', $root . '/database/database.sqlite');
echo "DB path: {$dbPath}\n";
if (!file_exists($dbPath)) {
    @touch($dbPath);
    @chmod($dbPath, 0664);
    echo "<span class='ok'>✓ database.sqlite created</span>\n";
} else {
    $size = filesize($dbPath);
    echo "<span class='ok'>✓ database.sqlite exists ({$size} bytes)</span>\n";
}

// 3. Run migrations
echo "\n<span class='info'>3. Running migrations...</span>\n";
try {
    $exitCode = Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();
    if ($exitCode === 0) {
        echo "<span class='ok'>✓ Migrations complete</span>\n";
    } else {
        echo "<span class='err'>✗ Migration failed (exit code: {$exitCode})</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ Migration error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 4. Run seeders
echo "\n<span class='info'>4. Seeding database...</span>\n";
try {
    $exitCode = Artisan::call('db:seed', ['--force' => true]);
    echo Artisan::output();
    if ($exitCode === 0) {
        echo "<span class='ok'>✓ Seeding complete</span>\n";
    } else {
        echo "<span class='err'>✗ Seeding failed (exit code: {$exitCode})</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ Seeding error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 5. Ensure lab & pharmacy users exist
echo "\n<span class='info'>5. Checking lab/pharmacy users...</span>\n";
try {
    $hospitalId = DB::table('hospitals')->where('is_active', true)->value('id')
        ?? DB::table('hospitals')->value('id');
    if ($hospitalId) {
        $password = \Illuminate\Support\Facades\Hash::make('password123');
        $now = now();
        $usersToCreate = [
            ['email' => 'lab@haztech.in', 'name' => 'Lab Technician', 'role' => 'lab_tech', 'dept' => 'Pathology', 'spec' => 'Clinical Pathology'],
            ['email' => 'pharmacy@haztech.in', 'name' => 'Pharmacist', 'role' => 'pharmacist', 'dept' => 'Pharmacy', 'spec' => 'Pharmacy'],
        ];
        foreach ($usersToCreate as $u) {
            if (!DB::table('users')->where('email', $u['email'])->exists()) {
                $userId = \Illuminate\Support\Str::uuid()->toString();
                $staffId = \Illuminate\Support\Str::uuid()->toString();
                DB::table('users')->insert([
                    'id' => $userId, 'name' => $u['name'], 'email' => $u['email'],
                    'email_verified_at' => $now, 'password' => $password,
                    'hospital_id' => $hospitalId, 'role' => $u['role'],
                    'staff_id' => $staffId, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('staff')->insert([
                    'id' => $staffId, 'hospital_id' => $hospitalId, 'user_id' => $userId,
                    'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'],
                    'department' => $u['dept'], 'specialization' => $u['spec'],
                    'is_active' => true, 'consultation_duration_default' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                echo "<span class='ok'>✓ Created {$u['name']} ({$u['email']})</span>\n";
            } else {
                echo "<span class='ok'>✓ {$u['name']} already exists</span>\n";
            }
        }
    } else {
        echo "<span class='err'>✗ No hospital found — cannot create users</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ User creation error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 6. Clear caches
echo "\n<span class='info'>6. Clearing caches...</span>\n";
try {
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    echo "<span class='ok'>✓ Config, route, and view caches cleared</span>\n";
} catch (Exception $e) {
    echo "<span class='err'>✗ Cache clear error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 7. Create storage link
echo "\n<span class='info'>7. Storage link...</span>\n";
try {
    Artisan::call('storage:link', ['--force' => true]);
    echo Artisan::output();
    echo "<span class='ok'>✓ Storage linked</span>\n";
} catch (Exception $e) {
    echo "<span class='info'>⊘ " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 8. Verify tables
echo "\n<span class='info'>8. Verifying tables...</span>\n";
try {
    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "  ✓ {$t->name}\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ Cannot read tables: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

// 7. Verify users
echo "\n<span class='info'>9. Checking users...</span>\n";
try {
    $users = DB::table('users')->get(['email', 'role']);
    echo "Users found: " . $users->count() . "\n";
    foreach ($users as $u) {
        echo "  ✓ {$u->email} ({$u->role})\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ Cannot read users: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

echo "\n\n<span class='ok'>=============================</span>\n";
echo "<span class='ok'>  DEPLOYMENT COMPLETE!</span>\n";
echo "<span class='ok'>=============================</span>\n\n";
echo "Login: <a href='https://medos.haztech.cloud/login' style='color:#60a5fa'>https://medos.haztech.cloud/login</a>\n";
echo "\n<span class='err'>⚠ DELETE this file (deploy.php) from File Manager now!</span>\n";
echo "</pre></body></html>";
