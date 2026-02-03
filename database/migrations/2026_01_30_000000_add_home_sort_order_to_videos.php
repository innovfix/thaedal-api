<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHomeSortOrderToVideos extends Migration
{
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'home_sort_order')) {
                $table->unsignedTinyInteger('home_sort_order')->nullable()->after('is_featured');
            }
        });
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'home_sort_order')) {
                $table->dropColumn('home_sort_order');
            }
        });
    }
}
