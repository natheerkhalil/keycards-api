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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->longtext("q");
            $table->longtext("a");
            
            $table->enum("status", [-1, 0, 1]);

            $table->timestamp("last_reviewed")->nullable();
            
            $table->unsignedBigInteger("folder");

            $table->string("creator");

            $table->foreign("folder")
                ->references("id")
                ->on("folders")
                ->onDelete("cascade");

            $table->foreign("creator")
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
        Schema::dropIfExists('cards');
    }
};
