<?php
require 'config/db.php';
$res = $conn->query('SHOW TABLES');
while($r = $res->fetch_array()) echo $r[0] . "\n";
