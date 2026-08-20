<?php
$files = ['includes/sidebar.php', 'admin/logindata.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = preg_replace('/\s*dark:[a-zA-Z0-9\-\/]+/', '', $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
