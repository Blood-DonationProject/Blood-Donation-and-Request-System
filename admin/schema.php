<?php
$conn = new mysqli('localhost', 'root', '', 'blood_donation_system');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$tables = ['donor', 'users', 'donor_assignments'];
foreach ($tables as $t) {
    echo "TABLE $t:\n";
    $res = $conn->query("SHOW COLUMNS FROM $t");
    while ($row = $res->fetch_assoc()) echo $row['Field'] . ", ";
    echo "\n";
}
?>
