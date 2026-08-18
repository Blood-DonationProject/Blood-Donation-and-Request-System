<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("SELECT id, request_id, donor_id, status, created_at, completed_at FROM donor_assignments");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
