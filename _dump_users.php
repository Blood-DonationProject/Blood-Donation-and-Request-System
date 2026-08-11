<?php
require_once __DIR__ . '/config/db.php';
$res = $conn->query("SHOW CREATE TABLE users");
if ($res) {
    echo $res->fetch_assoc()['Create Table'];
}
?>
