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
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string("name", 255);

            $table->string("style", 255)->default("sea");

            $table->unsignedBigInteger("parent")->nullable();

            $table->string("creator");

            $table->foreign("creator")->references("username")->on("users")->onDelete("cascade");
            $table->foreign("parent")->references("id")->on("folders")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
