<?php

include "auth_check.php";
include "../config/db.php";

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM blood_request WHERE id = ?");
$stmt->bind_param('i', $id);

if($stmt->execute())
{
    $stmt->close();
    header("Location: requests.php");
    exit;
}
else
{
    echo "Delete Failed";
}
$stmt->close();
?>