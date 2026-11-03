<?php
/* One-off E2E tokens for the smoke test of the 7-item batch. */
$studentRole = App\Models\User::where('role', 'student')->first();
$adminRole = App\Models\User::where('role', 'admin')->first();

if (! $studentRole || ! $adminRole) {
    echo json_encode(['error' => 'missing user', 'student' => (bool) $studentRole, 'admin' => (bool) $adminRole]);
    exit;
}

/* Fresh tokens so the test doesn't share state. */
$studentRole->tokens()->where('name', 'like', 'e2e-%')->delete();
$adminRole->tokens()->where('name', 'like', 'e2e-%')->delete();

$st = $studentRole->createToken('e2e-student-' . time())->plainTextToken;
$ad = $adminRole->createToken('e2e-admin-' . time())->plainTextToken;

echo json_encode([
    'student_user_id' => $studentRole->id,
    'student_name' => $studentRole->name,
    'student_token' => $st,
    'admin_user_id' => $adminRole->id,
    'admin_token' => $ad,
]);
