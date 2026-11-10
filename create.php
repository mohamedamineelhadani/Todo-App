<?php
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require_post();

$username = post_str('username');
$email    = strtolower(post_str('email'));
$password = post_str('password', false);

if ($username === '' || $email === '' || $password === '') {
    json_out(['ok' => false, 'message' => 'All fields are required.'], 422);
}
$len = mb_strlen($username);
if ($len < 2 || $len > 50) {
    json_out(['ok' => false, 'message' => 'Username must be 2–50 characters.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    json_out(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
}
if ($err = validate_password($password)) {
    json_out(['ok' => false, 'message' => $err], 422);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$recoveryCode = make_recovery_code();                       // shown to the user once
$recoveryHash = password_hash(normalize_recovery_code($recoveryCode), PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, recovery_hash) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $email, $passwordHash, $recoveryHash);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) { // duplicate email (UNIQUE) — also safe against race conditions
        json_out(['ok' => false, 'message' => 'This email is already registered.'], 409);
    }
    throw $e;
}

json_out(['ok' => true, 'message' => 'Account created!', 'recovery_code' => $recoveryCode], 201);
