<?php
include "auth_check.php";
include "../config/db.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: edit.php');
    exit;
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    header('Location: edit.php');
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

if (isset($_POST['update']))
{ 
    $patient_name=$_POST['patient_name'];
    $blood_group=$_POST['blood_group'];
    $hospital=$_POST['hospital'];
    $department=$_POST['department'];
    $units_required=$_POST['units_required'];
    $status=$_POST['status'];

            
    $updateStmt = $conn->prepare("UPDATE requests SET patient_name = ?, blood_group = ?, hospital = ?, department = ?, units_required = ?, status = ? WHERE id = ?");
    $updateStmt->bind_param('sssisii', $patient_name, $blood_group, $hospital, $department, $units_required, $status, $id);
    $updateStmt->execute();
    $updateStmt->close();

    header('Location: requests.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<script>
    (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
</script>
<script>
    tailwind.config = { darkMode: 'class' }
</script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="../assets/js/translations.js"></script>
<script src="../assets/js/i18n.js"></script>
<link rel="stylesheet" href="../assets/css/myanmar-font.css">
<style id="dark-mode-styles">
    html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
    html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
    html.dark body { background-color: #111827 !important; color: #e5e7eb; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
    html.dark label { color: #d1d5db !important; }
</style>
</head>

<body class="bg-gray-100 dark:bg-gray-900">

<div class="max-w-xl mx-auto mt-10 bg-white p-6 rounded shadow">

<h2 class="text-2xl font-bold mb-5">
Edit Request
</h2>

<form method="POST">

<input
type="text"
name="patient_name"
value="<?= $row['patient_name']?>"
class="w-full border p-2 mb-3">

<input
type="text"
name="blood_group"
value="<?= $row['blood_group']?>"
class="w-full border p-2 mb-3">

<input
type="text"
name="hospital"
value="<?= $row['hospital']?>"
class="w-full border p-2 mb-3">

<input
type="text"
name="department"
value="<?= $row['department']?>"
class="w-full border p-2 mb-3">

<input
type="number"
name="units_required"
value="<?= $row['units_required']?>"
class="w-full border p-2 mb-3">

<select name="status" class="w-full border p-2 mb-3">

<option <?=($row['status']=="Critical")?"selected":"";?>>
Critical
</option>

<option <?=($row['status']=="Pending")?"selected":"";?>>
Pending
</option>

<option <?=($row['status']=="Fulfilled")?"selected":"";?>>
Fulfilled
</option>

<option <?=($row['status']=="In Progress")?"selected":"";?>>
In Progress
</option>

</select>

<button
name="update"
class="bg-blue-600 text-white px-5 py-2 rounded">
Update
</button>

</form>

</div>

<script>
(function() {
  var KEY = 'bloodlife-theme';
  function getTheme() { return localStorage.getItem(KEY) || 'light'; }
  function apply(t) {
    if (t === 'dark') document.documentElement.classList.add('dark');
    else document.documentElement.classList.remove('dark');
    document.querySelectorAll('.theme-toggle-select').forEach(function(s){ s.value = t; });
  }
  apply(getTheme());
})();
</script>

</body>
</html>