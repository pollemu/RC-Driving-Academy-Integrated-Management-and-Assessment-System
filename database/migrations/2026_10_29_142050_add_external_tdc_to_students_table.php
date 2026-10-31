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
        Schema::table('students', function (Blueprint $table) {
            $table->string('tdc_source')->default('in_house')->after('gender'); // in_house | external
            $table->string('tdc_issuer')->nullable()->after('tdc_source');      // driving school name
            $table->date('tdc_completion_date')->nullable()->after('tdc_issuer');
            $table->string('tdc_proof_path')->nullable()->after('tdc_completion_date');
            $table->boolean('tdc_verified')->default(false)->after('tdc_proof_path');
            $table->unsignedBigInteger('tdc_verified_by')->nullable()->after('tdc_verified');
            $table->timestamp('tdc_verified_at')->nullable()->after('tdc_verified_by');
            $table->unsignedBigInteger('tdc_reviewed_by')->nullable()->after('tdc_verified_at');
            $table->text('tdc_rejection_reason')->nullable()->after('tdc_reviewed_by');
            $table->timestamp('tdc_submitted_at')->nullable()->after('tdc_rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'tdc_source', 'tdc_issuer', 'tdc_completion_date', 'tdc_proof_path',
                'tdc_verified', 'tdc_verified_by', 'tdc_verified_at',
                'tdc_reviewed_by', 'tdc_rejection_reason', 'tdc_submitted_at',
            ]);
        });
    }
};
