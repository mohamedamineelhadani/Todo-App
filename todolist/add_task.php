<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';
require_post();
$uid = require_login();
check_csrf();

$task     = post_str('task');
$priority = post_str('priority') ?: 'medium';
$due      = clean_date(post_str('due_date'));

if ($task === '')                      json_out(['ok' => false, 'message' => 'Task cannot be empty.'], 422);
if (mb_strlen($task) > 500)            json_out(['ok' => false, 'message' => 'Task is too long (max 500 characters).'], 422);
if (!in_array($priority, PRIORITIES, true)) json_out(['ok' => false, 'message' => 'Invalid priority.'], 422);
if ($due === false)                    json_out(['ok' => false, 'message' => 'Invalid due date.'], 422);

$stmt = $conn->prepare("INSERT INTO tasks (user_id, task, priority, due_date, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param('isss', $uid, $task, $priority, $due);
$stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

json_out(['ok' => true, 'task' => fetch_task($conn, $id, $uid)], 201);
