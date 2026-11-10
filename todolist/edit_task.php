<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';
require_post();
$uid = require_login();
check_csrf();

$id       = post_int('id');
$task     = post_str('task');
$priority = post_str('priority') ?: 'medium';
$due      = clean_date(post_str('due_date'));

if ($task === '')                      json_out(['ok' => false, 'message' => 'Task cannot be empty.'], 422);
if (mb_strlen($task) > 500)            json_out(['ok' => false, 'message' => 'Task is too long (max 500 characters).'], 422);
if (!in_array($priority, PRIORITIES, true)) json_out(['ok' => false, 'message' => 'Invalid priority.'], 422);
if ($due === false)                    json_out(['ok' => false, 'message' => 'Invalid due date.'], 422);

$stmt = $conn->prepare("UPDATE tasks SET task = ?, priority = ?, due_date = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param('sssii', $task, $priority, $due, $id, $uid);
$stmt->execute();
$stmt->close();

$row = fetch_task($conn, $id, $uid);
if (!$row) json_out(['ok' => false, 'message' => 'Task not found.'], 404);
json_out(['ok' => true, 'task' => $row]);
