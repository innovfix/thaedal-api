<?php
/**
 * Add is_featured column to videos table and verify all admin features.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== THAEDAL ADMIN PANEL HEALTH CHECK ===\n\n";

// 1) Add is_featured column if missing
echo "1. Checking videos.is_featured column...\n";
if (!Schema::hasColumn('videos', 'is_featured')) {
    Schema::table('videos', function ($table) {
        $table->boolean('is_featured')->default(false)->after('is_published');
    });
    echo "   ✅ Added is_featured column\n";
} else {
    echo "   ✅ Column already exists\n";
}

// 2) Check home_topics table exists
echo "\n2. Checking home_topics table...\n";
if (Schema::hasTable('home_topics')) {
    $topicCount = DB::table('home_topics')->count();
    echo "   ✅ Table exists ({$topicCount} topics)\n";
} else {
    echo "   ❌ Table missing - creating...\n";
    Schema::create('home_topics', function ($table) {
        $table->uuid('id')->primary();
        $table->string('title');
        $table->string('slug')->nullable();
        $table->uuid('category_id')->nullable();
        $table->integer('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    echo "   ✅ Created home_topics table\n";
}

// 3) Check home_topic_videos pivot table
echo "\n3. Checking home_topic_videos pivot table...\n";
if (Schema::hasTable('home_topic_videos')) {
    echo "   ✅ Table exists\n";
} else {
    echo "   ❌ Table missing - creating...\n";
    Schema::create('home_topic_videos', function ($table) {
        $table->uuid('home_topic_id');
        $table->uuid('video_id');
        $table->integer('sort_order')->default(0);
        $table->timestamps();
        $table->primary(['home_topic_id', 'video_id']);
    });
    echo "   ✅ Created home_topic_videos table\n";
}

// 4) Check key controllers exist
echo "\n4. Checking Admin Controllers...\n";
$controllers = [
    'DashboardController' => 'app/Http/Controllers/Admin/DashboardController.php',
    'UserController' => 'app/Http/Controllers/Admin/UserController.php',
    'VideoController' => 'app/Http/Controllers/Admin/VideoController.php',
    'CategoryController' => 'app/Http/Controllers/Admin/CategoryController.php',
    'SubscriptionController' => 'app/Http/Controllers/Admin/SubscriptionController.php',
    'PaymentController' => 'app/Http/Controllers/Admin/PaymentController.php',
    'PaymentSettingsController' => 'app/Http/Controllers/Admin/PaymentSettingsController.php',
    'NotificationController' => 'app/Http/Controllers/Admin/NotificationController.php',
    'HomeSetupController' => 'app/Http/Controllers/Admin/HomeSetupController.php',
    'CreatorController' => 'app/Http/Controllers/Admin/CreatorController.php',
];

foreach ($controllers as $name => $path) {
    $fullPath = base_path($path);
    if (file_exists($fullPath)) {
        echo "   ✅ {$name}\n";
    } else {
        echo "   ❌ {$name} MISSING\n";
    }
}

// 5) Check key views exist
echo "\n5. Checking Admin Views...\n";
$views = [
    'Dashboard' => 'resources/views/admin/dashboard.blade.php',
    'Users Index' => 'resources/views/admin/users/index.blade.php',
    'Videos Index' => 'resources/views/admin/videos/index.blade.php',
    'Subscriptions Index' => 'resources/views/admin/subscriptions/index.blade.php',
    'Payments Index' => 'resources/views/admin/payments/index.blade.php',
    'Payments Settings' => 'resources/views/admin/payments/settings.blade.php',
    'Notifications Index' => 'resources/views/admin/notifications/index.blade.php',
    'Notifications Create' => 'resources/views/admin/notifications/create.blade.php',
    'Home Setup Index' => 'resources/views/admin/home/index.blade.php',
];

foreach ($views as $name => $path) {
    $fullPath = base_path($path);
    if (file_exists($fullPath)) {
        echo "   ✅ {$name}\n";
    } else {
        echo "   ❌ {$name} MISSING\n";
    }
}

// 6) Check services
echo "\n6. Checking Services...\n";
$services = [
    'OneSignalService' => 'app/Services/OneSignalService.php',
];

foreach ($services as $name => $path) {
    $fullPath = base_path($path);
    if (file_exists($fullPath)) {
        echo "   ✅ {$name}\n";
    } else {
        echo "   ❌ {$name} MISSING\n";
    }
}

// 7) Check .env keys
echo "\n7. Checking Environment Configuration...\n";
$envKeys = [
    'RAZORPAY_KEY_ID' => config('services.razorpay.key_id'),
    'RAZORPAY_KEY_SECRET' => config('services.razorpay.key_secret') ? '(set)' : '(empty)',
    'ONESIGNAL_APP_ID' => config('services.onesignal.app_id'),
    'ONESIGNAL_REST_API_KEY' => config('services.onesignal.rest_api_key') ? '(set)' : '(empty)',
];

foreach ($envKeys as $key => $value) {
    $status = !empty($value) && $value !== '(empty)' ? '✅' : '❌';
    echo "   {$status} {$key}: {$value}\n";
}

// 8) Check database tables
echo "\n8. Checking Database Tables...\n";
$tables = ['users', 'videos', 'categories', 'subscriptions', 'payments', 'creators', 'admins'];
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "   ✅ {$table} ({$count} rows)\n";
    } else {
        echo "   ❌ {$table} MISSING\n";
    }
}

// 9) PHP syntax check on key files
echo "\n9. PHP Syntax Check...\n";
$filesToCheck = [
    'app/Http/Controllers/Admin/DashboardController.php',
    'app/Http/Controllers/Admin/NotificationController.php',
    'app/Http/Controllers/Admin/HomeSetupController.php',
    'app/Http/Controllers/Admin/PaymentSettingsController.php',
    'app/Services/OneSignalService.php',
];

$allSyntaxOk = true;
foreach ($filesToCheck as $file) {
    $fullPath = base_path($file);
    if (file_exists($fullPath)) {
        $output = [];
        $result = 0;
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $result);
        if ($result === 0) {
            echo "   ✅ {$file}\n";
        } else {
            echo "   ❌ {$file}: " . implode(' ', $output) . "\n";
            $allSyntaxOk = false;
        }
    }
}

// 10) Clear caches
echo "\n10. Clearing Caches...\n";
Artisan::call('config:clear');
echo "   ✅ Config cleared\n";
Artisan::call('view:clear');
echo "   ✅ Views cleared\n";
Artisan::call('route:clear');
echo "   ✅ Routes cleared\n";

echo "\n=== HEALTH CHECK COMPLETE ===\n";
