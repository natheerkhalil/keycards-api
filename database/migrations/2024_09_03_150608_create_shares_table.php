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
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean("accepted")->default(false);
            $table->boolean("view_only")->default(false);

            $table->boolean("cards");
            $table->boolean("children");

            $table->string("sharer");
            $table->string("receiver");
            $table->unsignedBigInteger("folder");

            $table->foreign("folder")
                ->references("id")
                ->on("folders")
                ->onDelete("cascade");

            $table->foreign("sharer")
                ->references("username")
                ->on("users")
                ->onDelete("cascade");

            $table->foreign("receiver")
                ->references("username")
                ->on("users")
                ->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
