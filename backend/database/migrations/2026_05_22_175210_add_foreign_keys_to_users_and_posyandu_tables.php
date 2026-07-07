<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posyandu', function (Blueprint $table) {
            $table->foreign('bidan_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('posyandu_id')->references('id')->on('posyandu')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['posyandu_id']);
        });

        Schema::table('posyandu', function (Blueprint $table) {
            $table->dropForeign(['bidan_id']);
        });
    }
};
