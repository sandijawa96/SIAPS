<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceGovernanceLogsTable extends Migration
{
    public function up()
    {
        Schema::create('attendance_governance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('category', 100)->index();
            $table->string('action', 100)->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('target_type', 100)->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['category', 'action', 'created_at'], 'agl_category_action_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_governance_logs');
    }
}
