<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PDC assessments now rate exactly three skills: steering, braking and side
 * mirror observation. The old six-skill columns stay in place for historical
 * rows, but `star_brake` / `note_brake` are the columns the app reads/writes
 * from now on. Existing rows inherit their speed rating as a best-effort
 * braking rating so old assessments still render sensibly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->tinyInteger('star_brake')->nullable()->after('star_steering');
            $table->string('note_brake', 120)->nullable()->after('star_brake');
        });

        DB::table('feedback')->whereNull('star_brake')->update([
            'star_brake' => DB::raw('star_speed'),
        ]);

        DB::table('feedback')->whereNull('note_brake')->update([
            'note_brake' => DB::raw('note_speed'),
        ]);
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn(['star_brake', 'note_brake']);
        });
    }
};
