<?php
include "auth_check.php";
include "../config/db.php";

$blood_groups = $conn->query("SELECT id, blood_gp_name FROM blood_groups");

if(isset($_POST['save']))
{
    $users_id = (int)($_SESSION['user_id'] ?? 0);
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $hospital = trim($_POST['hospital'] ?? '');
    $units = (int)$_POST['units'];
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO blood_request (users_id, blood_groups_id, units, hospital, required_date, status) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('iiisss', $users_id, $blood_groups_id, $units, $hospital, $required_date, $status);

    if($stmt->execute())
    {
        $stmt->close();
        header("Location: requests.php");
        exit;
    }
    else
    {
        echo "Error : ".$conn->error;
    }
    $stmt->close();
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

<h2 class="text-2xl font-bold mb-5">New Blood Request</h2>

<form method="POST">

<select name="blood_groups_id" class="w-full border p-2 mb-3" required>
    <option value="">-- Select Blood Group --</option>
    <?php if ($blood_groups): while ($bg = $blood_groups->fetch_assoc()): ?>
    <option value="<?= $bg['id'] ?>"><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
    <?php endwhile; endif; ?>
</select>

<input type="text" name="hospital" placeholder="Hospital Name"
class="w-full border p-2 mb-3" required>

<input type="number" name="units" placeholder="Units Needed" min="1"
class="w-full border p-2 mb-3" required>

<input type="date" name="required_date" class="w-full border p-2 mb-3">

<select name="status" class="w-full border p-2 mb-3">
<option value="Pending">Pending</option>
<option value="Approved">Approved</option>
<option value="Completed">Completed</option>
<option value="Rejected">Rejected</option>
</select>

<button name="save"
class="bg-red-700 text-white px-5 py-2 rounded">
Save
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
        document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
          var sun = btn.querySelector('.theme-icon-sun');
          var moon = btn.querySelector('.theme-icon-moon');
          if (sun) sun.style.display = t === 'dark' ? 'none' : 'inline';
          if (moon) moon.style.display = t === 'dark' ? 'inline' : 'none';
        });
      }
      apply(getTheme());
      window.toggleTheme = function() {
        var current = localStorage.getItem(KEY) || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        apply(next);
      };
    })();
    </script>

</body>
</html>