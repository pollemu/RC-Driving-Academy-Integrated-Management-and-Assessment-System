<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_schedules', function (Blueprint $table) {
            $table->string('status')->default('confirmed')->after('booked_at');
        });

        \Illuminate\Support\Facades\DB::table('student_schedules')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('student_schedules', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
