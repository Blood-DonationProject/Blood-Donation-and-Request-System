<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname ="blood_donation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset for Myanmar/Unicode support
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
//echo "Connected successfully";
?>