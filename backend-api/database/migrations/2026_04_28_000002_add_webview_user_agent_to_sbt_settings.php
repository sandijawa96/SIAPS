<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sbt_settings')) {
            return;
        }

        Schema::table('sbt_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sbt_settings', 'webview_user_agent')) {
                $table->string('webview_user_agent', 255)
                    ->nullable()
                    ->after('exam_host');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sbt_settings') || !Schema::hasColumn('sbt_settings', 'webview_user_agent')) {
            return;
        }

        Schema::table('sbt_settings', function (Blueprint $table) {
            $table->dropColumn('webview_user_agent');
        });
    }
};
