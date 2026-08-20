<?php
$files = glob('admin/*.php');
foreach($files as $file) {
    if(basename($file) == 'assignments.php') continue;
    $content = file_get_contents($file);
    if(strpos($content, 'id="dark-mode-styles"') !== false) {
        $content = preg_replace('/html\.dark \.w-64\.bg-white\s*\{[^}]+\}/', '', $content);
        $content = preg_replace('/html\.dark header\.bg-white(?:,\s*html\.dark header\.bg-white\.border-b)?\s*\{[^}]+\}/', '', $content);
        $content = preg_replace('/html\.dark nav\.bg-white(?:,\s*html\.dark nav\.bg-white\.shadow-md)?\s*\{[^}]+\}/', '', $content);
        
        $content = str_replace('html:not(.dark) .bg-gray-50 {', 'html:not(.dark) .bg-gray-50:not(.sidebar):not(nav):not(nav *) {', $content);
        $content = str_replace('html.dark .bg-white {', 'html.dark .bg-white:not(.sidebar):not(nav) {', $content);
        $content = str_replace('html.dark .text-gray-900, html.dark .text-gray-800 {', 'html.dark .text-gray-900:not(.sidebar *):not(nav *), html.dark .text-gray-800:not(.sidebar *):not(nav *) {', $content);
        $content = str_replace('html.dark .text-gray-700 {', 'html.dark .text-gray-700:not(.sidebar *):not(nav *) {', $content);
        $content = str_replace('html.dark .text-gray-600 {', 'html.dark .text-gray-600:not(.sidebar *):not(nav *) {', $content);
        $content = str_replace('html.dark .text-gray-500 {', 'html.dark .text-gray-500:not(.sidebar *):not(nav *) {', $content);
        
        $content = str_replace('html.dark .bg-gray-50, html.dark .bg-gray-100 {', 'html.dark .bg-gray-50:not(.sidebar *):not(nav *), html.dark .bg-gray-100:not(.sidebar *):not(nav *) {', $content);
        
        $content = str_replace('html.dark .border-gray-200, html.dark .border-2.border-gray-200, html.dark .border {', 'html.dark .border-gray-200:not(.sidebar):not(nav), html.dark .border-2.border-gray-200:not(.sidebar):not(nav), html.dark .border:not(.sidebar):not(nav) {', $content);
        $content = str_replace('html.dark .border-t {', 'html.dark .border-t:not(.sidebar *) {', $content);
        $content = str_replace('html.dark .bg-red-50 {', 'html.dark .bg-red-50:not(.sidebar *) {', $content);
        
        file_put_contents($file, $content);
        echo "Updated: " . $file . PHP_EOL;
    }
}
?>
