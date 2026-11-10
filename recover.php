<?php
// Replaces find.php. Passwords are hashed, so they can never be "shown" —
// instead the user proves ownership with email + recovery code and sets a NEW password.
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require_post();

$email       = strtolower(post_str('email'));
$code        = normalize_recovery_code(post_str('code'));
$newPassword = post_str('new_password', false);
$ip          = client_ip();

if ($email === '' || $code === '' || $newPassword === '') {
    json_out(['ok' => false, 'message' => 'All fields are required.'], 422);
}
if ($err = validate_password($newPassword)) {
    json_out(['ok' => false, 'message' => $err], 422);
}
if (is_throttled($conn, $email, $ip)) {
    json_out(['ok' => false, 'message' => 'Too many attempts. Try again in 15 minutes.'], 429);
}

$stmt = $conn->prepare("SELECT id, recovery_hash FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

$hash = $user['recovery_hash'] ?? password_hash('dummy-code', PASSWORD_DEFAULT);
if (!(password_verify($code, $hash) && $user !== null)) {
    log_attempt($conn, $email, $ip);
    json_out(['ok' => false, 'message' => 'Email or recovery code is incorrect.'], 401);
}

// Set the new password and rotate the recovery code (each code works only once).
$newCode     = make_recovery_code();
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$recoveryHash = password_hash(normalize_recovery_code($newCode), PASSWORD_DEFAULT);

$up = $conn->prepare("UPDATE users SET password = ?, recovery_hash = ? WHERE id = ?");
$up->bind_param('ssi', $passwordHash, $recoveryHash, $user['id']);
$up->execute();
$up->close();

clear_attempts($conn, $email);
json_out(['ok' => true, 'message' => 'Password updated!', 'recovery_code' => $newCode]);
