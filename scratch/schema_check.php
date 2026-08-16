<?php
require_once __DIR__ . '/../config/db.php';
$tables = ['users', 'blood_request', 'donor', 'notifications', 'donor_assignments'];
foreach($tables as $table) {
    echo "$table columns:\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    while($row = $res->fetch_assoc()) {
        echo "- " . $row['Field'] . "\n";
    }
    echo "\n";
}
