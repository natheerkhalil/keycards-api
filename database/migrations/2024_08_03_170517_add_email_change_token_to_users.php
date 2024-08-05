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
            $table->longtext("email_change_token")->nullable();
            $table->timestamp("email_change_token_sent_at")->nullable();
            $table->string("new_email", "255")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_change_token');
            $table->dropColumn('email_change_token_sent_at');
            $table->dropColumn('new_email');
        });
    }
};
