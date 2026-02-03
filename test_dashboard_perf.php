<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Http\Request;

echo "=== Dashboard Performance Analytics Smoke Test ===\n";

$metrics = ['views', 'watch_time', 'likes', 'composite'];
$ranges = ['today', 'week', 'month', 'custom'];

foreach ($metrics as $metric) {
    foreach ($ranges as $range) {
        $query = ['metric' => $metric, 'perf_range' => $range];
        if ($range === 'custom') {
            $query['perf_from'] = now()->subDays(7)->toDateString();
            $query['perf_to'] = now()->toDateString();
        }
        $request = Request::create('/admin/dashboard', 'GET', $query);
        try {
            $controller = app(DashboardController::class);
            $response = $controller->index($request);
            $viewData = $response->getData();
            $topVideos = $viewData['topVideos'] ?? [];
            $categories = $viewData['categoryPerformance'] ?? [];
            echo "Metric={$metric}, Range={$range}: topVideos=" . count($topVideos) . ", categories=" . count($categories) . "\n";
        } catch (Throwable $e) {
            echo "Metric={$metric}, Range={$range}: ERROR - " . $e->getMessage() . "\n";
        }
    }
}
