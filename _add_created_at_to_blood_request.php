<?php
require_once 'config/db.php';
$conn->query("ALTER TABLE blood_request ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
echo "Column added";
