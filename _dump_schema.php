<?php
require_once __DIR__ . '/config/db.php';
$tables = ['users','blood_groups','donor','blood_request','donor_assignments','donation_history','notifications','email_logs'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $res = $conn->query("SHOW CREATE TABLE `$t`");
    if ($res && $row = $res->fetch_assoc()) {
        echo $row['Create Table'] . "\n\n";
    } else {
        echo "Error or table not found: " . $conn->error . "\n\n";
    }
}
?>
