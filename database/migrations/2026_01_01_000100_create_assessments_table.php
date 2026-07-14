<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->unique()->constrained('feedback')->onDelete('cascade');
            $table->enum('classification', ['fast', 'average', 'needs']);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('recommendation')->nullable();
            $table->boolean('override_applied')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
