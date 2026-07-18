<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->string('note_steering', 120)->nullable()->after('star_steering');
            $table->string('note_speed', 120)->nullable()->after('star_speed');
            $table->string('note_lane', 120)->nullable()->after('star_lane');
            $table->string('note_parking', 120)->nullable()->after('star_parking');
            $table->string('note_safety', 120)->nullable()->after('star_safety');
            $table->string('note_mirror', 120)->nullable()->after('star_mirror');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn([
                'note_steering', 'note_speed', 'note_lane',
                'note_parking', 'note_safety', 'note_mirror',
            ]);
        });
    }
};
