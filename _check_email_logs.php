<?php
$conn = new mysqli('localhost', 'root', '', 'blood_donation_system');
if ($conn->connect_error) {
    file_put_contents('db_out.txt', "Error: " . $conn->connect_error);
    exit;
}
$res = $conn->query("SHOW COLUMNS FROM email_logs");
$out = "";
if ($res) {
    while ($row = $res->fetch_assoc()) $out .= $row['Field'] . "\n";
} else {
    $out = "Query error: " . $conn->error;
}
file_put_contents('db_out.txt', $out);
