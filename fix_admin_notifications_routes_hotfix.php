<?php
/**
 * Hotfix: ensure admin notifications routes exist in routes/web.php.
 */

function backup_file(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    @copy($path, $bak);
}

$routesPath = '/var/www/thaedal/api/routes/web.php';
backup_file($routesPath);

$routes = file_exists($routesPath) ? file_get_contents($routesPath) : '';
if (!$routes) {
    echo "routes/web.php not found\n";
    exit(1);
}

if (strpos($routes, "Route::get('notifications'") === false) {
    $needle = "Route::resource('payments', PaymentController::class)->only(['index', 'show']);";
    $block = $needle . "\n\n        // Notifications (OneSignal)\n        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');\n        Route::get('notifications/new', [NotificationController::class, 'create'])->name('notifications.create');\n        Route::post('notifications', [NotificationController::class, 'send'])->name('notifications.send');\n        Route::get('notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');\n        Route::put('notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');";

    if (strpos($routes, $needle) !== false) {
        $routes = str_replace($needle, $block, $routes);
        file_put_contents($routesPath, $routes);
        echo "Inserted notifications routes.\n";
    } else {
        echo "Could not find insertion point in routes/web.php\n";
        exit(2);
    }
} else {
    echo "Notifications routes already present.\n";
}

