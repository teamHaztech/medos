<?php
/**
 * MedOS Deployment Script
 * Access via: https://medos.haztech.cloud/deploy.php?key=haztech2026
 * Run ONCE after git deploy, then DELETE this file.
 */

if (($_GET['key'] ?? '') !== 'haztech2026') { die('Unauthorized'); }

echo "<pre>\n";
echo "=== MedOS Deployment ===\n\n";

// 1. Copy .env
$root = dirname(__DIR__);
if (!file_exists($root . '/.env')) {
    if (copy($root . '/.env.production', $root . '/.env')) {
        echo "✓ .env created from .env.production\n";
        // Fix the DB path
        $env = file_get_contents($root . '/.env');
        $env = str_replace('/home/YOUR_HOSTINGER_USER/public_html/medos', $root, $env);
        file_put_contents($root . '/.env', $env);
        echo "✓ .env DB path updated\n";
    } else {
        echo "✗ Failed to create .env\n";
    }
} else {
    echo "⊘ .env already exists\n";
}

// 2. Generate app key
echo "\n--- Generating App Key ---\n";
$output = shell_exec("cd {$root} && php artisan key:generate --force 2>&1");
echo $output ?? "✗ Failed\n";

// 3. Create database
$dbPath = $root . '/database/database.sqlite';
if (!file_exists($dbPath)) {
    touch($dbPath);
    chmod($dbPath, 0664);
    echo "✓ database.sqlite created\n";
} else {
    echo "⊘ database.sqlite already exists\n";
}

// 4. Run migrations
echo "\n--- Running Migrations ---\n";
$output = shell_exec("cd {$root} && php artisan migrate --force 2>&1");
echo $output ?? "✗ Failed\n";

// 5. Run seeders
echo "\n--- Seeding Database ---\n";
$output = shell_exec("cd {$root} && php artisan db:seed --force 2>&1");
echo $output ?? "✗ Failed\n";

// 6. Cache config
echo "\n--- Caching ---\n";
$output = shell_exec("cd {$root} && php artisan config:cache 2>&1");
echo $output ?? "";
$output = shell_exec("cd {$root} && php artisan route:cache 2>&1");
echo $output ?? "";
$output = shell_exec("cd {$root} && php artisan view:cache 2>&1");
echo $output ?? "";

// 7. Storage link
echo "\n--- Storage Link ---\n";
$output = shell_exec("cd {$root} && php artisan storage:link 2>&1");
echo $output ?? "";

echo "\n\n=== DONE ===\n";
echo "Now DELETE this file from File Manager!\n";
echo "Login at: https://medos.haztech.cloud/login\n";
echo "</pre>";
