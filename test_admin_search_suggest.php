<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\SearchController;
use Illuminate\Http\Request;

$controller = app(SearchController::class);

$tests = [
    ['type' => 'users', 'q' => 'a'],
    ['type' => 'users', 'q' => 'an'],
    ['type' => 'videos', 'q' => 'a'],
    ['type' => 'videos', 'q' => 'ra'],
    ['type' => 'creators', 'q' => 'a'],
    ['type' => 'payments', 'q' => 'pay_'],
    ['type' => 'subscriptions', 'q' => 'sub_'],
];

foreach ($tests as $test) {
    $request = Request::create('/admin/search-suggestions', 'GET', $test);
    $response = $controller->suggest($request);
    $payload = $response->getData(true);
    echo "Type={$test['type']} q={$test['q']} -> " . count($payload['items'] ?? []) . " items\n";
}
