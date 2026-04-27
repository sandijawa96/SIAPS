<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoleHierarchiesPgsql extends Migration
{
    public function up()
    {
        Schema::create('role_hierarchies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_role_id');
            $table->unsignedBigInteger('child_role_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('child_role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('role_hierarchies');
    }
}
