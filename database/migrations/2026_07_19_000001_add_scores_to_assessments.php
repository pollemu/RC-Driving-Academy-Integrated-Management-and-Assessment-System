<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->decimal('exam_score', 5, 2)->nullable()->after('confidence_score');
            $table->decimal('score', 5, 2)->nullable()->after('exam_score');
            $table->boolean('passed')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['exam_score', 'score', 'passed']);
        });
    }
};
