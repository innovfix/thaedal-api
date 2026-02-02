<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('uninstalled_at')->nullable()->after('last_login_at');
            $table->index('uninstalled_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['uninstalled_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('uninstalled_at');
        });
    }
};
