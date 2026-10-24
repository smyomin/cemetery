<?php
$LANGS = ['en', 'my'];
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'en');
if (!in_array($lang, $LANGS, true)) { $lang = 'en'; }
if (isset($_GET['lang']) && isset($_SESSION)) { $_SESSION['lang'] = $lang; }
$STRINGS = array_merge(
    require __DIR__ . '/lang/en.php',
    require __DIR__ . '/lang/admin.en.php',
    require __DIR__ . '/lang/' . $lang . '.php',
    require __DIR__ . '/lang/admin.' . $lang . '.php'
);
function t(string $key): string {
    global $STRINGS;
    return htmlspecialchars($STRINGS[$key] ?? $key);
}
function tf(string $key, ...$args): string {
    global $STRINGS;
    return htmlspecialchars(sprintf($STRINGS[$key] ?? $key, ...$args));
}