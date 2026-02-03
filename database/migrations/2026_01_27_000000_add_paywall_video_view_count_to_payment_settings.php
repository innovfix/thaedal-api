<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaywallVideoViewCountToPaymentSettings extends Migration
{
    public function up()
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_settings', 'paywall_video_view_count')) {
                $table->unsignedBigInteger('paywall_video_view_count')->default(0)->after('paywall_video_path');
            }
        });
    }

    public function down()
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_settings', 'paywall_video_view_count')) {
                $table->dropColumn('paywall_video_view_count');
            }
        });
    }
}
