<?php
require_once __DIR__ . '/config/db.php';
$res = $conn->query("SHOW CREATE TABLE blood_request");
if ($res) {
    echo $res->fetch_assoc()['Create Table'];
}
?>
