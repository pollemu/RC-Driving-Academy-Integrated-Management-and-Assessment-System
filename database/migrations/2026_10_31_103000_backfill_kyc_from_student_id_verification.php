<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* The users.kyc_* columns were added in 2026_10_29_143700 but never wired up.
   This backfill seeds KYC from the older students.id_status flow so students
   whose Valid ID was already approved keep their verified standing when the
   new flow (users.kyc_*) replaces it. One-shot data migration. */
return new class extends Migration
{
    public function up(): void
    {
        // Verified: previously approved valid IDs, plus reviewed_at/by carried over.
        DB::statement("
            UPDATE users
            SET kyc_status = 'verified',
                id_type = (SELECT s.id_document_type FROM students s WHERE s.user_id = users.id),
                id_document_path = (SELECT s.document_path FROM students s WHERE s.user_id = users.id),
                kyc_submitted_at = (SELECT s.id_submitted_at FROM students s WHERE s.user_id = users.id),
                kyc_verified_at = (SELECT s.id_reviewed_at FROM students s WHERE s.user_id = users.id),
                kyc_verified_by = (SELECT s.id_reviewed_by FROM students s WHERE s.user_id = users.id),
                liveness_passed = TRUE,
                auto_verified = FALSE
            WHERE EXISTS (
                SELECT 1 FROM students s
                WHERE s.user_id = users.id
                  AND s.id_status = 'approved'
                  AND s.document_path IS NOT NULL
            )
        ");

        // Rejected: keep the reason so the student can read it and resubmit.
        DB::statement("
            UPDATE users
            SET kyc_status = 'rejected',
                id_type = (SELECT s.id_document_type FROM students s WHERE s.user_id = users.id),
                id_document_path = (SELECT s.document_path FROM students s WHERE s.user_id = users.id),
                kyc_submitted_at = (SELECT s.id_submitted_at FROM students s WHERE s.user_id = users.id),
                kyc_rejection_reason = (SELECT s.id_rejection_reason FROM students s WHERE s.user_id = users.id)
            WHERE EXISTS (
                SELECT 1 FROM students s
                WHERE s.user_id = users.id
                  AND s.id_status = 'rejected'
                  AND s.document_path IS NOT NULL
            )
        ");

        // Pending: still waiting on review — carried over as pending.
        DB::statement("
            UPDATE users
            SET kyc_status = 'pending',
                id_type = (SELECT s.id_document_type FROM students s WHERE s.user_id = users.id),
                id_document_path = (SELECT s.document_path FROM students s WHERE s.user_id = users.id),
                kyc_submitted_at = (SELECT s.id_submitted_at FROM students s WHERE s.user_id = users.id)
            WHERE EXISTS (
                SELECT 1 FROM students s
                WHERE s.user_id = users.id
                  AND s.id_status = 'pending'
                  AND s.document_path IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        // Data migration — only ever run forward.
    }
};
