<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('broadcast_campaigns')) {
            return;
        }

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('broadcast_campaigns', 'message_category')) {
                $table->string('message_category', 30)
                    ->default('announcement')
                    ->after('type')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('broadcast_campaigns')) {
            return;
        }

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('broadcast_campaigns', 'message_category')) {
                $table->dropColumn('message_category');
            }
        });
    }
};
