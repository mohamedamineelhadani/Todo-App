<?php
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require_post();

$email    = strtolower(post_str('email'));
$password = post_str('password', false); // never trim passwords
$ip       = client_ip();

if ($email === '' || $password === '') {
    json_out(['ok' => false, 'message' => 'Email and password are required.'], 422);
}

if (is_throttled($conn, $email, $ip)) {
    json_out(['ok' => false, 'message' => 'Too many attempts. Try again in 15 minutes.'], 429);
}

$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

// Verify against a dummy hash when the email is unknown so timing doesn't reveal which emails exist.
$hash = $user['password'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
$ok   = password_verify($password, $hash) && $user !== null;

if (!$ok) {
    log_attempt($conn, $email, $ip);
    json_out(['ok' => false, 'message' => 'Incorrect email or password.'], 401);
}

// Upgrade the hash silently if PHP's default algorithm/cost has changed.
if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    $new = password_hash($password, PASSWORD_DEFAULT);
    $up  = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $up->bind_param('si', $new, $user['id']);
    $up->execute();
    $up->close();
}

clear_attempts($conn, $email);
session_regenerate_id(true);          // prevents session fixation
unset($_SESSION['csrf']);             // fresh CSRF token for the new session
$_SESSION['user_id']  = (int)$user['id'];
$_SESSION['username'] = $user['username'];

json_out(['ok' => true, 'redirect' => 'todolist/todo.php']);
