<?php
$file = 'includes/navbar.php';
$content = file_get_contents($file);

$replacements = [
    'hover:border-red-300:border-red-500' => 'hover:border-red-300',
    'hover:bg-red-50:bg-gray-600' => 'hover:bg-red-50',
    'hover:bg-gray-50:bg-gray-700' => 'hover:bg-gray-50',
    'hover:bg-red-100:bg-red-900/50' => 'hover:bg-red-100',
    'hover:text-red-700:text-red-300' => 'hover:text-red-700'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Fixed hover states in navbar.php\n";
?>
