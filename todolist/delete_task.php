<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';
require_post();
$uid = require_login();
check_csrf();

$id = post_int('id');
$stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if (!$deleted) json_out(['ok' => false, 'message' => 'Task not found.'], 404);
json_out(['ok' => true]);
