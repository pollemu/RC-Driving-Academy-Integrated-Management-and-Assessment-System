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
- **[CORRECTION]** No confirmation email or SMS is sent (no email/SMS
  integration exists — see Section 10).

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
- **[CORRECTION]** There is **no confirmation prompt** before saving.
- **[CORRECTION]** Promoting to Instructor does **not** require or capture a
  specialization (TDC/PDC/both). The system creates an Instructor row but
  leaves `specialization` empty; it must be set elsewhere (or via seeder) if
  needed. The role-change endpoint only accepts `{ role }`.

Step 5: Instructor Assignment (student → instructor)
- **[NEW in current build]** Admin can assign a Student to an Instructor from
  the Students table ("Assign" button → select instructor → save). This sets
  `students.instructor_id` and is shown in the instructor's "My Students" list.
- This is a separate action from role promotion.

Step 6: Audit Log
- **[CORRECTION]** There is **no role-change audit log** (no who/old/new/
  timestamp recorded).

Step 7: Notification
- **[CORRECTION]** No notification is sent on role change (no email/SMS).

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
- **[CORRECTION]** There is **no separate TDC completion/exam-score recording**.
  Attendance is the only TDC-specific record. "TDC completed" is inferred
  indirectly (presence of an assessment linked to a TDC feedback), but the
  instructor assessment UI is oriented to PDC. PDC assessment is detailed in
  Section 4.

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
  raw text, stars, notes, classification, and suggestions.

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
- **[CORRECTION]** Eligibility is simply: the student has an enrollment with
  status `active` or `completed` for the course, and no certificate has already
  been issued for that course.
- The originally specified gates — "required number of PDC sessions completed",
  "no unresolved safety flags", and an explicit "full payment" check — are
  **NOT implemented**. (A PDC 4W student who paid only the 50% downpayment is
  still certifiable because their enrollment is `active`.)

Step 2: Issue Certificate
- Admin selects the student + course and issues. System generates a
  certificate number, stores the record (issued_by, issued_at, status=
  "active"), and can print a PDF.

Step 3: Student Access
- Student views/downloads the certificate from their dashboard.

Step 4: Revoke
- Admin can revoke a certificate (status → "revoked").

===========================================================
9. REPORTING FLOW (ADMIN)
===========================================================

Step 1: Admin accesses the Reports module.
Step 2: **[CORRECTION]** The backend provides a single stats endpoint
  returning aggregate counts only:
  * total students
  * total enrollments
  * verified payments
  * total revenue
  * completed sessions
- The UI shows six report cards (Enrollment Summary, Revenue, Learner
  Performance, Attendance, Instructor, Certification), but their "Generate"
  buttons are **non-functional placeholders** — no detailed report endpoints
  exist yet.

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

--- D. SMS GATEWAY API --- [NOT IMPLEMENTED]
Purpose (per original spec): OTP, reminders, payment/cert notifications.
- **No SMS code exists in the codebase.** Documented but absent.

--- E. EMAIL / SMTP SERVICE --- [NOT IMPLEMENTED]
Purpose (per original spec): registration, payment receipts, schedule, cert.
- **No email-sending code exists.** Documented but absent.

--- F. GOOGLE MAPS / GEOLOCATION --- [NOT IMPLEMENTED]
Purpose (per original spec): show PDC routes / venues on a map.
- **No maps integration.** Venue is a free-text field only.

--- Summary Table of Actual API Touchpoints ---
  Registration        -> none (no SMS/email)
  Role/Account Mgmt    -> none (no notification)
  Enrollment           -> PayMongo (student), Admin cash path (offline)
  Scheduling           -> none (no reminders)
  PDC Assessment        -> NLP Microservice (internal, async)
  Payment              -> PayMongo (GCash/BDO) + Admin manual verify
  Certification        -> Local file storage (PDF) only
  Reporting            -> none (stats only)
  SMS / Email / Maps    -> NOT IMPLEMENTED

===========================================================
END OF DOCUMENT
