<?php
require_once __DIR__ . '/../config/db.php';
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    while ($row = $tables->fetch_row()) {
        $table = $row[0];
        $res = $conn->query("SHOW CREATE TABLE `$table`");
        if ($res) {
            echo "--- Table: $table ---\n";
            echo $res->fetch_assoc()['Create Table'] . ";\n\n";
        }
    }
} else {
    echo "Error: " . $conn->error;
}
?>
