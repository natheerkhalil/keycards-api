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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean("banned_share")->default(false);
            $table->boolean("banned_feedback")->default(false);
            $table->boolean("banned_create")->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn("banned_share");
            $table->dropColumn("banned_feedback");
            $table->dropColumn("banned_create");
        });
    }
};
