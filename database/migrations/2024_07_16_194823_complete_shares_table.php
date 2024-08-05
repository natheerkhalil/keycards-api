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
        Schema::table("shares", function (Blueprint $table) {
            $table->string("sender");
            $table->string("receiver");

            $table->foreign("sender")->references("username")->on("users")->onDelete("cascade");
            $table->foreign("receiver")->references("username")->on("users")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("shares", function (Blueprint $table) {
            $table->dropColumn("sender");
            $table->dropColumn("receive+r");
        });
    }
};
