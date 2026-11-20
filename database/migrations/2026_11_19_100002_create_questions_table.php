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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('tier', 1);
            $table->string('type', 20);
            $table->string('objective', 100)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('prompt');
            $table->text('context')->nullable();
            $table->jsonb('spec');
            $table->text('explanation')->nullable();
            $table->text('solution')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('content_hash', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module_id', 'key']);
            $table->index(['module_id', 'tier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
