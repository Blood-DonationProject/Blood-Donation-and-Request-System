<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("SELECT * FROM donation_history");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
