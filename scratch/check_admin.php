<?php
require_once __DIR__ . '/../config/db.php';
$result = $conn->query("SELECT * FROM users");
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
?>
