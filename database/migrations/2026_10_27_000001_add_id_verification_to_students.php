<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Adds the valid-ID verification workflow to students. The file itself lives
   on the private disk (students.document_path) and is only ever served through
   the authenticated API — never from /public. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('id_status')->default('none')->index();
            $table->string('id_document_type')->nullable();
            $table->string('id_document_name')->nullable();
            $table->string('id_document_mime')->nullable();
            $table->timestamp('id_submitted_at')->nullable();
            $table->timestamp('id_reviewed_at')->nullable();
            $table->unsignedBigInteger('id_reviewed_by')->nullable();
            $table->text('id_rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['id_status']);
            $table->dropColumn([
                'id_status',
                'id_document_type',
                'id_document_name',
                'id_document_mime',
                'id_submitted_at',
                'id_reviewed_at',
                'id_reviewed_by',
                'id_rejection_reason',
            ]);
        });
    }
};
