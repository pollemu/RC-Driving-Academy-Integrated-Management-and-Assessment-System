<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_status')->default('unverified')->after('role'); // unverified | pending | verified | rejected
            $table->string('id_type')->nullable()->after('kyc_status');
            $table->string('id_document_path')->nullable()->after('id_type');
            $table->string('selfie_path')->nullable()->after('id_document_path');
            $table->decimal('face_match_score', 5, 4)->nullable()->after('selfie_path');
            $table->boolean('liveness_passed')->default(false)->after('face_match_score');
            $table->boolean('auto_verified')->default(false)->after('liveness_passed');
            $table->timestamp('kyc_submitted_at')->nullable()->after('auto_verified');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_submitted_at');
            $table->unsignedBigInteger('kyc_verified_by')->nullable()->after('kyc_verified_at');
            $table->text('kyc_rejection_reason')->nullable()->after('kyc_verified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_status', 'id_type', 'id_document_path', 'selfie_path',
                'face_match_score', 'liveness_passed', 'auto_verified',
                'kyc_submitted_at', 'kyc_verified_at', 'kyc_verified_by', 'kyc_rejection_reason',
            ]);
        });
    }
};
