<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // TDC runs in fixed 2-day blocks (Mon–Tue, Wed–Thu, Fri–Sat), so a
            // session holds both its first and last day. PDC leaves it null.
            $table->date('end_date')->nullable()->after('date');
            // Blocks created by the rolling TDC generator (admin can cancel them).
            $table->boolean('auto_generated')->default(false)->after('status');
        });

        // A TDC class is a group class: it can exist before an instructor is
        // assigned, and students don't need an instructor to see availability.
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'auto_generated']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable(false)->change();
        });
    }
};
