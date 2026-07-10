<?php
/**
 * BUTIK POS — Git Pull Script
 * Visit: https://kasablankaboutique.my.id/pull.php?secret=BoutiquePull2026
 * This ONLY pulls latest code — does NOT touch the database.
 */

define('PULL_SECRET', 'BoutiquePull2026');

if (!isset($_GET['secret']) || $_GET['secret'] !== PULL_SECRET) {
    http_response_code(403);
    die('Forbidden.');
}

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (@ob_end_flush()) {}

$root = dirname(__DIR__); // one level up from public/

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Pull</title></head>';
echo '<body><pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#0f0;">';
echo "=== BUTIK POS — GIT PULL ===\n";
echo "Root: $root\n\n";
flush();

// ── git pull ──────────────────────────────────────────────────────────────────
echo "[ 1 ] Running git pull ...\n";
$output = shell_exec("cd " . escapeshellarg($root) . " && git pull origin main 2>&1");
echo htmlspecialchars($output) . "\n";
flush();

// ── Clear Laravel view/config cache ───────────────────────────────────────────
echo "[ 2 ] Clearing Laravel cache ...\n";
try {
    require $root . '/vendor/autoload.php';
    putenv('APP_BASE_PATH=' . $root);
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    foreach (['view:clear', 'config:clear', 'route:clear'] as $cmd) {
        Illuminate\Support\Facades\Artisan::call($cmd);
        echo "  OK: $cmd\n";
    }
} catch (Throwable $e) {
    echo "  SKIP cache clear: " . htmlspecialchars($e->getMessage()) . "\n";
}
echo "\n";
flush();

echo "=== DONE ===\n";
echo "Hard-refresh your browser (Cmd+Shift+R) after this.\n";
echo '</pre></body></html>';
