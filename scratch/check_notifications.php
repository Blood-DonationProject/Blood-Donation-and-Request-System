<?php
require 'config/db.php';
$r = $conn->query('SELECT type, title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 5');
if($r) {
    while($row = $r->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Query failed: " . $conn->error;
}
