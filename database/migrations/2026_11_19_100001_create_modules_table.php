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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->longText('body');
            $table->jsonb('objectives');
            $table->jsonb('sources');
            $table->jsonb('tested_on');
            $table->jsonb('quiz_sizes');
            $table->string('content_updated_on', 20)->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
