<?php
$LANGS = ['en', 'my'];
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, $LANGS, true)) { $lang = 'en'; }
$STRINGS = array_merge(require __DIR__ . '/lang/en.php', require __DIR__ . '/lang/' . $lang . '.php');
function t(string $key): string {
    global $STRINGS;
    return htmlspecialchars($STRINGS[$key] ?? $key);
}
function tf(string $key, ...$args): string {
    global $STRINGS;
    return htmlspecialchars(sprintf($STRINGS[$key] ?? $key, ...$args));
}
