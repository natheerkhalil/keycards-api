<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("videos", function (Blueprint $table) {
            $table->string("username");
            $table->foreign("username")->references("username")->on("users")->onDelete("cascade");

            $table->string("title");
            $table->string("thumbnail");
            $table->string("start");
            $table->string("end");
            $table->longtext("skip");
            $table->boolean("fav");
            $table->longtext("desc");

            $table->unsignedBigInteger("playlist");
            $table->foreign("playlist")->references("id")->on("playlists")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("playlists", function (Blueprint $table) {
            $table->dropColumn("username");

            $table->dropColumn("title");
            $table->dropColumn("thumbnail");
            $table->dropColumn("start");
            $table->dropColumn("end");
            $table->dropColumn("skip");
            $table->dropColumn("fav");
            $table->dropColumn("desc");

            $table->dropColumn("playlist");
        });
    }
};
