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
        Schema::table('shares', function (Blueprint $table) {
            $table->unsignedBigInteger("video");
            $table->foreign("video")->references("id")->on("videos")->onDelete("cascade")->onUpdate("restrict");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropForeign(['video']);
            $table->dropColumn("video");
        });
    }
};
