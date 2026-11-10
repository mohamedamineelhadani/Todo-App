<?php
declare(strict_types=1);

// ---- Session (hardened cookie) ------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

const PRIORITIES = ['low', 'medium', 'high'];

// ---- Output helpers ------------------------------------------------------
function json_out(array $data, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Never leak SQL/PHP errors to the browser; log them and return a generic message.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e) {
    error_log($e);
    json_out(['ok' => false, 'message' => 'Server error. Please try again.'], 500);
});

// ---- Request helpers -----------------------------------------------------
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_out(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function post_str(string $key, bool $trim = true): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    return $trim ? trim($v) : $v;
}

function post_int(string $key): int
{
    $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v === null ? 0 : (int)$v;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// ---- Auth + CSRF ---------------------------------------------------------
function require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        json_out(['ok' => false, 'message' => 'Please log in again.'], 401);
    }
    return (int)$_SESSION['user_id'];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_out(['ok' => false, 'message' => 'Session expired. Reload the page.'], 403);
    }
}

// ---- Validation ----------------------------------------------------------
function validate_password(string $pw): ?string
{
    if (strlen($pw) < 8)                 return 'Password must be at least 8 characters.';
    if (!preg_match('/[a-z]/', $pw))     return 'Add a lowercase letter.';
    if (!preg_match('/[A-Z]/', $pw))     return 'Add an uppercase letter.';
    if (!preg_match('/[0-9]/', $pw))     return 'Add a number.';
    return null;
}

// Returns null for "no date", false for an invalid date, or 'Y-m-d'.
function clean_date(string $v)
{
    if ($v === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : false;
}

// ---- Recovery codes (generated on the server, shown once, stored hashed) -
function make_recovery_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I confusion
    $out = '';
    for ($i = 0; $i < 16; $i++) {
        $out .= $alphabet[random_int(0, 31)];
        if ($i % 4 === 3 && $i < 15) $out .= '-';
    }
    return $out;
}

function normalize_recovery_code(string $code): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
}

// ---- Brute-force throttling ---------------------------------------------
function is_throttled(mysqli $conn, string $email, string $ip): bool
{
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    $stmt = $conn->prepare(
        "SELECT SUM(ip = ?) AS by_ip, SUM(email = ?) AS by_email
           FROM login_attempts
          WHERE attempted_at > (NOW() - INTERVAL 15 MINUTE) AND (ip = ? OR email = ?)"
    );
    $stmt->bind_param('ssss', $ip, $email, $ip, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['by_ip'] ?? 0) >= 20 || (int)($row['by_email'] ?? 0) >= 5;
}

function log_attempt(mysqli $conn, string $email, string $ip): void
{
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip, email) VALUES (?, ?)");
    $stmt->bind_param('ss', $ip, $email);
    $stmt->execute();
    $stmt->close();
}

function clear_attempts(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

// ---- Task helpers --------------------------------------------------------
function task_row(array $r): array
{
    return [
        'id'         => (int)$r['id'],
        'task'       => (string)$r['task'],
        'completed'  => (int)$r['completed'],
        'priority'   => (string)$r['priority'],
        'due_date'   => $r['due_date'],
        'created_at' => (string)$r['created_at'],
    ];
}

function fetch_task(mysqli $conn, int $id, int $uid): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, task, completed, priority, due_date, created_at FROM tasks WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? task_row($row) : null;
}
