<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->date('preferred_date')->nullable()->after('preferred_gender');
            $table->foreignId('preferred_instructor_id')->nullable()->after('preferred_date')->constrained('instructors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_instructor_id');
            $table->dropColumn('preferred_date');
        });
    }
};
