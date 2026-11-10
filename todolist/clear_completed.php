<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';
require_post();
$uid = require_login();
check_csrf();

$stmt = $conn->prepare("DELETE FROM tasks WHERE user_id = ? AND completed = 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$count = $stmt->affected_rows;
$stmt->close();

json_out(['ok' => true, 'deleted' => $count]);
