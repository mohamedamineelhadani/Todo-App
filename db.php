<?php
// Database connection. Always require helpers.php BEFORE this file
// (it installs the error handler that turns exceptions into a clean 500).

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Override with environment variables in production; the defaults suit local XAMPP/WAMP.
$conn = new mysqli(
    getenv('DB_HOST') ?: 'localhost',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    getenv('DB_NAME') ?: 'todo_app'
);
$conn->set_charset('utf8mb4');
