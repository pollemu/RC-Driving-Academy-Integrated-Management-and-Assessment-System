<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('specialization');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('license_type');
            $table->string('preferred_instructor_gender')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('gender');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['gender', 'preferred_instructor_gender']);
        });
    }
};
