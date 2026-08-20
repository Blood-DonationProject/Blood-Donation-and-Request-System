<?php
$file = 'admin/logindata.php';
$content = file_get_contents($file);
$content = str_replace('hover:bg-red-50:bg-red-900/30 hover:text-red-700:text-red-400', 'hover:bg-red-50 hover:text-red-700', $content);
file_put_contents($file, $content);
echo "Fixed logindata.php hover states\n";
?>
