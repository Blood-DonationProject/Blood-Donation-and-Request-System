<?php
/**
 * Language System - BloodLife
 * PHP session-based bilingual support (EN / MY)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set language from GET param or session or default to 'en'
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'my'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';

// Load language file
$translations = [];
$langFile = __DIR__ . '/../lang/' . $lang . '.php';
if (file_exists($langFile)) {
    require $langFile;
}

/**
 * Translate a key. Falls back to key itself if not found.
 */
function t($key, $fallback = null) {
    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    return $fallback !== null ? $fallback : $key;
}

function current_lang() {
    return $_SESSION['lang'] ?? 'en';
}

function other_lang() {
    return current_lang() === 'en' ? 'my' : 'en';
}

function lang_switch_url() {
    $params = $_GET;
    $params['lang'] = other_lang();
    unset($params['lang_switch']); // avoid double switch
    return '?' . http_build_query($params);
}

/**
 * Output the PHP language as a JS global so i18n.js can use it.
 * Also output the full translation map for client-side data-i18n elements.
 */
function lang_js_globals() {
    global $translations, $lang;
    $json = json_encode($translations, JSON_UNESCAPED_UNICODE);
    echo "<script>window.PHP_LANG='" . htmlspecialchars($lang) . "';window.PHP_TRANSLATIONS={$json};</script>";
}
