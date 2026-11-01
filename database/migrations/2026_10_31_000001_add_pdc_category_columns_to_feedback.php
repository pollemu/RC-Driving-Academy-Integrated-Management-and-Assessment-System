<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDC assessments now rate the five official behind-the-wheel categories:
 *
 *   engine  — Engine & Gear Control        (new columns)
 *   brake   — Smooth Braking & Stopping    (star_brake/note_brake exist)
 *   turning — Turning & Lane Discipline    (new columns)
 *   parking — Backing & Precision Parking  (star_parking/note_parking already
 *                                           exist — the legacy six-skill set
 *                                           already rated parking, so those
 *                                           columns are repurposed wholesale)
 *   traffic — Traffic Rules & Speed Management (new columns)
 *
 * The legacy star_steering/note_steering and star_mirror/note_mirror columns
 * stay in place (nullable) so historical assessments keep rendering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->tinyInteger('star_engine')->nullable()->after('star_brake');
            $table->string('note_engine', 120)->nullable()->after('star_engine');
            $table->tinyInteger('star_turning')->nullable()->after('star_mirror');
            $table->string('note_turning', 120)->nullable()->after('star_turning');
            $table->tinyInteger('star_traffic')->nullable()->after('note_safety');
            $table->string('note_traffic', 120)->nullable()->after('star_traffic');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn([
                'star_engine', 'note_engine',
                'star_turning', 'note_turning',
                'star_traffic', 'note_traffic',
            ]);
        });
    }
};
