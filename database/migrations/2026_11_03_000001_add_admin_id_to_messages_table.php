<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The support chat used to be student <-> bot (or instructor) only. When a
   student asks to "talk to a human" the admin now answers inside the same
   thread, so every admin-authored message is stamped with which staff member
   sent it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('instructor_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });
    }
};
