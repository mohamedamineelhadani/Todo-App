<?php
// Marks a task done / not done. (This is the file that was wrongly named todo.php before.)
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';
require_post();
$uid = require_login();
check_csrf();

$id = post_int('id');
$stmt = $conn->prepare("UPDATE tasks SET completed = 1 - completed WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$stmt->close();

$task = fetch_task($conn, $id, $uid);
if (!$task) json_out(['ok' => false, 'message' => 'Task not found.'], 404);
json_out(['ok' => true, 'task' => $task]);
