<?php
$file = 'admin/logindata.php';
$content = file_get_contents($file);

// Add .sidebar class and dark mode backgrounds
$content = str_replace('<div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">', '<div class="sidebar w-64 bg-white dark:bg-gray-900 shadow-lg hover:ring-1 hover:ring-pink-500/20 hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto transition-colors duration-300">', $content);

// Borders
$content = str_replace('<div class="p-6 border-b border-gray-200">', '<div class="p-6 border-b border-gray-200 dark:border-gray-700">', $content);
$content = str_replace('<div class="p-4 border-t border-gray-200">', '<div class="p-4 border-t border-gray-200 dark:border-gray-700">', $content);

// Text colors
$content = str_replace('<h1 class="font-bold text-lg text-red-700">', '<h1 class="font-bold text-lg text-red-700 dark:text-red-400">', $content);
$content = str_replace('<p class="text-xs text-gray-500">CRUD Panel</p>', '<p class="text-xs text-gray-600 dark:text-gray-400">CRUD Panel</p>', $content);

// Links
$content = str_replace('text-gray-700 hover:bg-gray-100 rounded-lg transition', 'text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/30 hover:text-red-700 dark:hover:text-red-400 hover:ring-1 hover:ring-pink-500/20 rounded-lg transition', $content);
$content = str_replace('bg-red-50 text-red-700 rounded-lg font-semibold', 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-semibold ring-1 ring-pink-500/30 rounded-lg transition', $content);

file_put_contents($file, $content);
echo "Updated logindata.php\n";
?>
