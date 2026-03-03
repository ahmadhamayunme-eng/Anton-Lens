<?php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/../app/' . str_replace('App\\', '', $class);
        $path = str_replace('\\', '/', $path) . '.php';
        if (file_exists($path)) require $path;
    }
});

$config = require __DIR__ . '/../config/config.php';
$pdo = App\Services\Database::pdo($config['db']);
$provider = new App\Services\PlaceholderScreenshotProvider();

$stmt = $pdo->query("SELECT * FROM jobs WHERE status='queued' AND run_after <= NOW() ORDER BY id ASC LIMIT 10");
$jobs = $stmt->fetchAll();
foreach ($jobs as $job) {
    $pdo->prepare("UPDATE jobs SET status='processing', attempts=attempts+1, updated_at=NOW() WHERE id=:id")->execute(['id' => $job['id']]);
    try {
        if ($job['type'] === 'screenshot_capture') {
            $payload = json_decode($job['payload_json'], true);
            $sizes = ['desktop' => [1366, 768], 'tablet' => [768, 1024], 'mobile' => [390, 844]];
            [$w, $h] = $sizes[$payload['device_preset'] ?? 'desktop'] ?? $sizes['desktop'];
            $dir = __DIR__ . '/../storage/screenshots/' . $payload['project_id'] . '/' . $payload['thread_id'];
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $rel = 'storage/screenshots/' . $payload['project_id'] . '/' . $payload['thread_id'] . '/' . time() . '.png';
            $meta = $provider->capture($payload['page_url'] ?? '', $w, $h, __DIR__ . '/../' . $rel);
            $pdo->prepare('INSERT INTO screenshots (thread_id,message_id,file_path,mime,width,height,created_at) VALUES (:t,:m,:f,:mime,:w,:h,NOW())')->execute([
                't' => $payload['thread_id'], 'm' => $payload['message_id'] ?? null, 'f' => $rel, 'mime' => $meta['mime'], 'w' => $meta['width'], 'h' => $meta['height']
            ]);
        }
        $pdo->prepare("UPDATE jobs SET status='done', updated_at=NOW() WHERE id=:id")->execute(['id' => $job['id']]);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE jobs SET status='failed', last_error=:err, updated_at=NOW() WHERE id=:id")->execute(['id' => $job['id'], 'err' => substr($e->getMessage(), 0, 1000)]);
    }
}
