<?php
/**
 * BUTIK POS — cPanel Deployment Setup
 * Visit: https://kasablankaboutique.my.id/deploy.php?secret=BoutiqueSetup2026
 * DELETE this file after setup is complete!
 */

define('DEPLOY_SECRET', 'BoutiqueSetup2026');

if (!isset($_GET['secret']) || $_GET['secret'] !== DEPLOY_SECRET) {
    http_response_code(403);
    die('Forbidden.');
}

// Flush output immediately so we can see progress
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (@ob_end_flush()) {}

$root = __DIR__;

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Deploy</title></head>';
echo '<body><pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;">';
echo "=== BUTIK POS DEPLOYMENT SETUP ===\n";
echo "Root: $root\n\n";
flush();

// ── Step 1: Check .env ──────────────────────────────────────────────────────
echo "[ STEP 1 ] Checking .env ...\n";
$dst = $root . '/.env';
if (!file_exists($dst)) {
    die("  FATAL: .env not found — upload the .env file manually via File Manager.\n</pre></body></html>");
}
echo "  OK: .env found\n\n";
flush();

// ── Step 2: Create storage dirs ─────────────────────────────────────────────
echo "[ STEP 2 ] Creating storage directories ...\n";
$dirs = [
    "$root/storage/app/public",
    "$root/storage/framework/cache/data",
    "$root/storage/framework/sessions",
    "$root/storage/framework/views",
    "$root/storage/logs",
    "$root/bootstrap/cache",
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true) ? print("  CREATED: $dir\n") : print("  FAILED: $dir\n");
    } else {
        chmod($dir, 0775);
        echo "  OK: $dir\n";
    }
}
echo "\n";
flush();

// ── Step 3: Load Laravel & run artisan via CLI ───────────────────────────────
echo "[ STEP 3 ] Loading Laravel ...\n";
$_SERVER['argv'] = [];
$_SERVER['argc'] = 0;

// Set the app base path explicitly so Laravel finds everything in public_html
putenv('APP_BASE_PATH=' . $root);

try {
    require $root . '/vendor/autoload.php';

    // Override base path before app boots
    $app = require $root . '/bootstrap/app.php';
    echo "  OK: Laravel booted\n\n";
} catch (Throwable $e) {
    echo "  FATAL: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo '</pre></body></html>';
    exit(1);
}
flush();

// ── Step 4: Migrations ───────────────────────────────────────────────────────
echo "[ STEP 4 ] Running migrations ...\n";
try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $status = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo Illuminate\Support\Facades\Artisan::output();
    echo "  Exit: $status\n\n";
} catch (Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . "\n\n";
}
flush();

// ── Step 5: Seed ─────────────────────────────────────────────────────────────
echo "[ STEP 5 ] Seeding database ...\n";
try {
    $status = Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
    echo Illuminate\Support\Facades\Artisan::output();
    echo "  Exit: $status\n\n";
} catch (Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . "\n\n";
}
flush();

// ── Step 6: Clear old cache then re-cache ────────────────────────────────────
echo "[ STEP 6 ] Caching config / routes / views ...\n";
foreach (['config:clear','route:clear','view:clear','config:cache','route:cache','view:cache'] as $cmd) {
    try {
        Illuminate\Support\Facades\Artisan::call($cmd);
        echo "  OK: $cmd\n";
    } catch (Throwable $e) {
        echo "  SKIP $cmd: " . $e->getMessage() . "\n";
    }
}
echo "\n";
flush();

// ── Done ─────────────────────────────────────────────────────────────────────
echo "=== SETUP COMPLETE ===\n\n";
echo "  LOGIN:\n";
echo "    Owner : owner@butik.test  / password\n";
echo "    Kasir : kasir@butik.test  / password\n\n";
echo "  ⚠ DELETE deploy.php from File Manager now!\n";
echo "  Visit: https://kasablankaboutique.my.id\n";
echo '</pre></body></html>';
