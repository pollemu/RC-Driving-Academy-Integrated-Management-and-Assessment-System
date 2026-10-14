<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_training_examples', function (Blueprint $table) {
            $table->id();
            $table->text('raw_text');
            $table->string('label', 20)->index(); // fast | average | needs
            $table->json('stars')->nullable();
            $table->json('notes')->nullable();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_training_examples');
    }
};