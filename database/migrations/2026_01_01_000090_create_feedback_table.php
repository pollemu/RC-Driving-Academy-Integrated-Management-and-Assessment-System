<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->text('raw_text');
            $table->tinyInteger('star_steering')->nullable();
            $table->tinyInteger('star_speed')->nullable();
            $table->tinyInteger('star_lane')->nullable();
            $table->tinyInteger('star_parking')->nullable();
            $table->tinyInteger('star_safety')->nullable();
            $table->tinyInteger('star_mirror')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
