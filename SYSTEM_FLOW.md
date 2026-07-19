# RC DRIVING ACADEMY — INTEGRATED MANAGEMENT AND ASSESSMENT SYSTEM (IMAS)
## SYSTEM FLOW DOCUMENTATION (AS-BUILT)

> This document describes how the system **actually behaves in its current
> implementation**, preserving the client's intended section structure. Items
> that differ from the original "intended" spec are noted inline as
> **[CORRECTION]** so the gap between plan and reality is explicit.

===========================================================
1. STUDENT REGISTRATION FLOW
===========================================================

Step 1: Access Registration Page
- Student navigates to the public sign-up page (signup.html).
- System displays a registration form.

Step 2: Fill Out Registration Form
- Student enters:
  * First Name + Last Name (combined into a single "Full Name")
  * Email Address
  * Contact Number
  * Password / Confirm Password
  * Consent checkbox
- **[CORRECTION]** The form does **not** collect a separate Username (login
  uses Email), Address, Date of Birth, or document uploads. The backend API
  *accepts* address/birthdate/license_type, but the sign-up form never sends
  them, so those fields are left empty for self-registered students. (Admin-
  created students via seeder do carry address/birthdate.)

Step 3: Form Validation
- System validates required fields and that password matches confirmation.
- System checks email uniqueness (no duplicate accounts).

Step 4: Account Creation
- Upon successful validation, system creates a User record and a linked
  Student record.
- Default role assigned automatically: "student".
- **[CORRECTION]** There is **no "Pending" approval state**. New accounts are
  created with `is_active = true` immediately and can log in right away. The
  users table uses a boolean `is_active` flag, not a status string like
  "Pending"/"Active".

Step 5: Confirmation
- System displays a success message and redirects to the Login page.
- **[UPDATED]** A registration notification is now dispatched through the
  NotificationService (see Section 10): an email (logged via MAIL_MAILER=log
  when no SMTP is configured) and an SMS (logged, not sent, until Semaphore
  keys are added). Every attempt is recorded in `notification_logs` so the
  send is verifiable even before provider keys exist.

Step 6: Student Login
- Student logs in with Email + Password.
- System authenticates and issues a Sanctum API token (8h expiry).
- On success, the SPA redirects to the Student Dashboard.

Step 7: Student Dashboard Access
- Student can:
  * Enroll in a driving course (TDC / PDC)
  * Book an available class session
  * View their booked sessions and attendance
  * View PDC assessment feedback/results
  * Pay (PayMongo GCash/BDO, or cash recorded by admin)
  * View/download certification once issued by admin
  * (Profile editing is limited in the current build)

===========================================================
2. ROLE MANAGEMENT FLOW (ADMIN-CONTROLLED)
===========================================================

Note: Only Administrators can assign or change user roles. There is no
self-registration path for Admin or Instructor accounts.

Step 1: Admin Login
- Admin logs in; system checks role = "admin" and redirects to Admin Dashboard.

Step 2: Access User Management Module
- Admin opens a Student's profile (from the Students table → "View").
- The profile has a "Set Role" control.

Step 3: Select a User to Modify
- Admin opens the user record and chooses a new role from a dropdown
  (student / instructor / admin).

Step 4: Assign/Change Role
- Admin picks the new role and saves.
- **[UPDATED]** The role-change endpoint now accepts `{ role, specialization,
  reason }`. When promoting to **Instructor**, the admin may set a
  `specialization` of TDC / PDC / Both (defaults to TDC); changing an existing
  instructor's specialization also works via this endpoint. There is still no
  confirmation prompt before saving (by design).

Step 5: Instructor Assignment (student → instructor)
- **[NEW in current build]** Admin can assign a Student to an Instructor from
  the Students table ("Assign" button → select instructor → save). This sets
  `students.instructor_id` and is shown in the instructor's "My Students" list.
- This is a separate action from role promotion.

Step 6: Audit Log
- **[NEW in current build]** Every role change is recorded in the
  `role_changes` table (user_id, changed_by, from_role, to_role, reason,
  timestamps). Admins can view the audit trail per user in the Student Profile
  modal ("Role change history"); the API is `GET /admin/users/role-changes`.

Step 7: Notification
- **[CORRECTION]** No notification is sent on role change (the notification
  subsystem covers registration, payment, and certificate events only).

Step 8: Access Update
- The affected user's access is determined by their role on next login.

===========================================================
3. INSTRUCTOR FLOW — SESSIONS & ATTENDANCE
===========================================================

Step 1: Instructor Login
- Instructor logs in; system checks role = "instructor" and redirects to the
  Instructor Dashboard.

Step 2: View Assigned Schedule
- Instructor sees the sessions assigned to them (created by Admin), showing
  date, time, venue, course, and capacity.

Step 3: View Their Students
- Instructor sees two groups in "My Students":
  * Students **assigned** to them by the admin (students.instructor_id).
  * Students **booked** into their sessions.

Step 4: Conduct Session & Mark Attendance
- For a session, the instructor opens it and marks each booked student
  Present / Late / Absent.
- Saving marks the session `done` and stores attendance records
  (student_id, schedule_id, status, recorded_at).

Step 5: TDC vs PDC Assessment
- **[UPDATED]** TDC assessments now carry real scores. The instructor feedback
  form accepts an optional `exam_score` (0–100). The system computes a composite
  `score` (0–100): practical star average (0–5 normalised) blended 50/50 with the
  exam score when both exist, otherwise whichever is present. A TDC assessment is
  marked `passed = true` when `score >= 75`. "TDC completed" for PDC gating now
  requires a **passing** TDC assessment (`passed = true`), not merely its
  existence. PDC assessments behave as before (attendance + star ratings + NLP);
  their `passed`/`score`/`exam_score` columns are populated the same way and feed
  the safety/certification gates in Section 8.

===========================================================
4. INSTRUCTOR FLOW — PDC ASSESSMENT (STAR RATINGS + NLP ENRICHMENT)
===========================================================

Step 1: Instructor Login (PDC)
- Instructor logs in and opens a PDC session with booked students.

Step 2: Conduct Practical Driving Session
- Instructor supervises the driving session and marks attendance.

Step 3: Submit Driving Assessment
- Instructor rates the student on **six skill categories** with 1–5 stars:
  Steering, Speed, Lane Discipline, Parking, Safety, Mirror & Observation.
- Instructor may also pick a preset note (Perfect / Average / Low) per
  category and type free-text observations.
- **[CORRECTION]** Classification is **not** derived from free text alone.
  The instant result is **rule-based** from the star averages
  (AssessmentSuggestion service): high averages → "fast", low → "needs",
  otherwise "average".

Step 4: Synchronous Result
- The rule-based classification + per-category training suggestions are saved
  immediately and shown to instructor and student.

Step 5: NLP Microservice Enrichment (async)
- A background job sends the free-text feedback to the NLP Flask microservice
  (/classify). The microservice returns a classification + confidence. If the
  NLP service is unavailable, this step is skipped gracefully (no failure).
- The enrichment updates the existing assessment (it does not create a second
  one) and posts the feedback to /train for continual training.

Step 6: Safety Override Check
- **[CORRECTION]** The safety override exists but uses **different keywords**
  than originally specified. The Laravel job checks for: bumangga, collision,
  near-collision, dangerous, hazard, crash, accident, took control, had to
  take control, emergency, unsafe speed, erratic, delikado, mapanganib,
  muntik na kaming bumangga. (The doc's "banggaan"/"aksidente" are NOT the
  actual triggers.) If detected, the result is forced to "needs" with
  override_applied = true.

Step 7: Store Assessment Result
- Final assessment is saved linked to the student's PDC session, including
  raw text, stars, notes, classification, suggestions, and now `exam_score`,
  `score`, and `passed` (see Section 3 Step 5 for the scoring rule).

Step 8: Return Result
- Instructor and student see the classification + suggestions on their
  dashboards.

Step 9: Progress Tracking
- Student progress aggregates assessment categories, scores, and weak-category
  suggestions over time.

===========================================================
5. ENROLLMENT FLOW
===========================================================

Step 1: Student selects course(s) from the dashboard
- Options: one TDC course (Face-to-Face or Online — single select), and/or a
  PDC course. A "bundle" is simply selecting both TDC + PDC in one cart.

Step 2: Prerequisite Check
- Only **one** TDC may be selected.
- PDC enrollment is blocked until TDC is **completed** (an assessment exists
  for a TDC session for that student).

Step 3: Enrollment Record Created (Pending)
- Enrollment is created with status **"pending"** (not "Enrolled").
- **[CORRECTION]** Status values are: `pending` → `active` (after verified
  payment) → `completed`. There is no "Enrolled" string.

Step 4: Payment Required to Activate
- The enrollment becomes `active` only after a verified payment covering the
  course downpayment (see Section 7). Until then the student cannot book.

Step 5: Scheduling
- Once active, the student can book into available sessions (Section 6).

===========================================================
6. SCHEDULING & BOOKING FLOW
===========================================================

Step 1: Admin Creates Sessions
- Admin opens Scheduling and creates a session: course, instructor, date,
  start/end time, venue, and max_students (TDC sessions typically 10, PDC
  sessions 1).

Step 2: Conflict Check
- **[CORRECTION]** The conflict check covers the **instructor only** (same
  instructor cannot have two overlapping sessions). There is **no vehicle or
  room availability check** — schedules store a free-text venue only.

Step 3: Student Books a Session
- **[CORRECTION]** Students **self-book** into open sessions (the admin does
  not manually assign individual students to a slot). Booking requires an
  `active` enrollment for that course and allows only **one upcoming booking**
  at a time.

Step 4: Booking Pending Admin Acceptance
- **[NEW in current build]** A new booking is created with status **"pending"**.
  It does **not** count toward session capacity until accepted.
- Admin reviews "Pending Bookings", where each lists the student, session,
  and the instructor assigned to that session. Admin **Accept** confirms the
  booking (status → "confirmed", counts toward capacity); **Reject** deletes it.

Step 5: Visibility
- Confirmed bookings appear on both the student's and instructor's dashboards.

===========================================================
7. PAYMENT FLOW
===========================================================

Step 1: Student selects course and proceeds to pay.
Step 2: System calculates amount (course fee / required downpayment).
Step 3: Student chooses method.
- **Student-facing:** GCash or BDO only, via PayMongo hosted checkout.
  (Methods validated as `gcash|bdo`.)
Step 4: PayMongo creates a checkout session; student is redirected to PayMongo
  to pay; webhook/callback verifies and activates the enrollment.

Step 5 (Admin Manual Path):
- **[NEW in current build]** Admin can **Record a Payment** directly (cash /
  GCash / BDO / OTC) from the Payments panel. This creates a payment already
  `verified`, activates the enrollment immediately, and lets the student book
  without PayMongo. This is the offline path used when PayMongo keys are not
  configured.
Step 6: Verification
- Admin can also manually **Verify** or **Reject** any pending payment;
  verifying activates the linked enrollment.

===========================================================
8. CERTIFICATION FLOW
===========================================================

Step 1: Admin opens Certifications → "Ready to Issue".
- **[UPDATED]** Eligibility is now evaluated by `CertGateService` and surfaced
  per-candidate with a checklist of requirements (shown in the UI):
  * Base rule: an enrollment with status `active`/`completed` for the course,
    and no certificate already issued.
  * TDC: additionally requires a **passing TDC assessment** (`passed = true`).
  * PDC: additionally requires:
    - **Completed PDC sessions** — configurable count (default **8**), measured
      as `confirmed` bookings whose schedule is `done`.
    - **No unresolved safety flag** — no PDC assessment with `override_applied`
      or classification `needs` (can be disabled via setting).
    - **Full payment settled** — verified payments for the course >= course fee
      (can be disabled via setting; PDC 4W still certifies only after the full
      fee is paid, not just the 50% downpayment).
- The thresholds (`pdc.required_sessions`, `cert.require_full_payment`,
  `cert.require_safety_clear`) are stored in `settings` and editable via
  `POST /admin/reports/cert-settings`.
- The originally specified gates are now **IMPLEMENTED**. If a candidate fails
  any gate, the admin may still **Force issue** (sends `force:1`); the missing
  requirements are returned in the 422 response for transparency.

Step 2: Issue Certificate
- Admin selects the student + course and issues (or Force-issues). System
  generates a certificate number, stores the record (issued_by, issued_at,
  status = "active"), can print a PDF, and dispatches a certificate-issued
  notification (email + SMS, see Section 10).

Step 3: Student Access
- Student views/downloads the certificate from their dashboard.

Step 4: Revoke
- Admin can revoke a certificate (status → "revoked").

===========================================================
9. REPORTING FLOW (ADMIN)
===========================================================

Step 1: Admin accesses the Reports module.
Step 2: **[UPDATED]** The Reports panel now renders **live data** from
  `GET /admin/reports` (no more placeholder "Generate" buttons). It shows:
  * Totals: students, instructors, enrollments, revenue, certifications.
  * **Enrollments by Course** (total / active / completed per course).
  * **Revenue by Month** (sum of verified payments, grouped by month).
  * **Payment Status** (verified / pending / rejected counts).
  * **Certifications Issued** (per course, issued vs revoked).
  * **Attendance Summary** (present / late / absent counts, sessions done).
  * **Assessment Outcomes** (passed/failed, fast/average/needs, safety-flagged).
- The headline dashboard counts still come from `GET /admin/dashboard`.

===========================================================
10. EXTERNAL API / THIRD-PARTY INTEGRATIONS
===========================================================

--- A. PAYMONGO API (Payments) --- [IMPLEMENTED]
Purpose: Online payments (GCash / BDO) via hosted checkout.
- Backend creates a Payment Intent / Checkout Session; student redirected;
  webhook verifies and activates enrollment. Requires PayMongo keys in .env.
- If keys are missing, the checkout returns a clean 502 and the admin
  manual-payment path (Section 7) is used instead.

--- B. NLP MICROSERVICE (Internal Flask API) --- [IMPLEMENTED]
Purpose: Classify PDC feedback text (fast/average/needs) with safety override.
- Laravel POSTs feedback to Flask /classify and /train. See Section 4 for the
  actual keywords and star-based primary classification. Runs on IMAS's own
  infrastructure; gracefully no-ops if unavailable.

--- C. FILE STORAGE --- [IMPLEMENTED]
Purpose: Store payment receipts (upload) and generated certificate PDFs.
- Uses local Laravel storage (receipts disk). Cloud storage (S3/Drive) is not
  wired up.

--- D. SMS GATEWAY API (Semaphore PH) --- [IMPLEMENTED, KEY-OPTIONAL]
Purpose (per original spec): OTP, reminders, payment/cert notifications.
- A pluggable `SemaphoreSmsChannel` sends via `https://api.semaphore.co/api/v4/messages`.
- **Graceful no-key behaviour:** if `SMS_ENABLED=false` or no `SEMAPHORE_API_KEY`
  is set, the message is **logged** (channel = "sms", sent = false) instead of
  sent, so the system runs fully without keys. Flip `SMS_ENABLED=true` and add
  the key in `.env` to go live. Phone numbers are normalised to PH international
  format (63…). Used for: registration welcome, payment received, certificate
  issued, and session reminders.

--- E. EMAIL / SMTP SERVICE (Laravel Mailer) --- [IMPLEMENTED]
Purpose (per original spec): registration, payment receipts, cert, reminders.
- An `EmailChannel` sends via Laravel's mailer (config/mail.php). With
  `MAIL_MAILER=log` (the dev default) mail is written to
  `storage/logs/laravel.log` rather than delivered — this is the intended
  no-key behaviour. Set a real mailer (SMTP/Resend/SES) + `MAIL_ENABLED=true` to
  deliver. Used for the same four events as SMS.

--- F. GOOGLE MAPS / GEOLOCATION --- [IMPLEMENTED, KEY-OPTIONAL]
Purpose (per original spec): show PDC routes / venues on a map.
- A `MapsChannel` builds Google Maps directions/embed URLs for a session venue.
  With `MAPS_ENABLED=false` or no `GOOGLE_MAPS_API_KEY`, the directions URL is
  still generated and **logged**, but no embedded map is resolved. Used by the
  session-reminder notification to include a route link to the venue.

--- Notification Log & Channel Status ---
- Every send (or logged attempt) is recorded in `notification_logs`
  (channel, event, recipient, message, sent, error, user). Admins view them in
  the new **Notifications** panel (`GET /admin/notifications`), which also shows
  whether each channel is currently Live or Disabled.

--- Summary Table of Actual API Touchpoints ---
  Registration        -> Email + SMS (logged until keys added)
  Role/Account Mgmt    -> none (audit log only)
  Enrollment           -> PayMongo (student), Admin cash path (offline)
  Scheduling           -> none on book; Maps link in reminders
  PDC Assessment        -> NLP Microservice (internal, async)
  Payment              -> PayMongo (GCash/BDO) + Admin manual verify + notification
  Certification        -> Local file storage (PDF) + notification + gated eligibility
  Reporting            -> live 6-card report endpoint (/admin/reports)
  SMS / Email / Maps    -> IMPLEMENTED (graceful no-key; wire via .env later)

==========================================================
12. STUDENT-FACING SELF-SERVICE: PREFERENCES, RESCHEDULE REQUESTS & AI SUPPORT CHAT [IMPLEMENTED]

These three features were added in the current batch to reduce admin
coordination load and give students self-service touchpoints. They are all
backed by the existing API + notification subsystem and require no external keys.

--- A. INSTRUCTOR-GENDER PREFERENCE (SOFT) ---
Purpose: let a student express a preferred instructor gender without it ever
restricting their bookings. It is a preference, honoured best-effort by admin.
- Storage: `students.gender` (their own) and
  `students.preferred_instructor_gender` (male | female | null).
- Captured at registration (`POST /api/register`, `gender` +
  `preferred_instructor_gender`) and editable by the student via
  `POST /api/me/preferences` (rendered in the student Profile → "My Preferences").
- Admin can also set an instructor's `gender` when assigning a role
  (`POST /api/admin/users/{id}/set-role`).
- Surfaced to instructors in **My Students** (`GET /api/instructor/students`
  returns `preferred_instructor_gender`) as a "Prefers male/female" badge, and to
  admin in the Student Requests list (instructor-preference requests).
- A student submits a formal preference *request* via
  `POST /api/student/requests/instructor-preference`; this both stores the value
  on the student and opens a pending `StudentRequest` for admin visibility, and
  fires a `requestReceived` notification to admin.

--- B. RESCHEDULE REQUESTS (STUDENT → ADMIN APPROVAL) ---
Purpose: student requests to move a confirmed booking to another open session;
admin approves/rejects. The original booking stays intact until approval.
- Student endpoints:
  - `GET  /api/student/requests`            -> their requests + statuses
  - `POST /api/student/requests/reschedule` -> {from_schedule_id, schedule_id, note}
    validates the source booking exists, courses match, and the target schedule is
    open with capacity (reuses the booking validation rules).
  - `DELETE /api/student/requests/{id}`     -> cancel a *pending* request only.
- Admin endpoints:
  - `GET  /api/admin/requests?status=pending` -> paginated list (filter by status).
  - `POST /api/admin/requests/{id}/accept`    -> for reschedules, moves the booking
    (StudentSchedule) from the old schedule to the new one, then notifies the student.
  - `POST /api/admin/requests/{id}/reject`    -> records an optional admin note,
    rejects the request, and notifies the student.
- Model `StudentRequest` (type: reschedule | instructor_preference;
  status: pending | accepted | rejected). Only one open reschedule per booking at a
  time is enforced.

--- C. AI SUPPORT CHATBOT (RULE-BASED) ---
Purpose: an always-available support assistant for students, answering common
questions about enrolling, TDC/PDC, payments, bookings, rescheduling, instructor
preferences, and certificates — and escalating to a human on request.
- No LLM key required: a data-driven keyword rule engine lives in
  `app/Http/Controllers/Api/Student/ChatController.php` (`BOT_RULES`).
- Student endpoints:
  - `GET  /api/student/chat`  -> message history (most recent first, capped).
  - `POST /api/student/chat`  -> saves the student's message, runs the rule engine,
    saves and returns the bot reply. The phrase "talk to human" flags the
    conversation for staff follow-up.
- History persists in the `messages` table (sender: student | bot | instructor),
  scoped per student. Frontend = a floating widget in the student dashboard.

--- Summary Table of Actual API Touchpoints (added) ---
  Instructor-gender pref -> stored on student + optional request + admin notified
  Reschedule request      -> student request → admin accept moves booking → notifies
  Support chat            -> rule-based bot, history in messages, human escalation

==========================================================
END OF DOCUMENT
