<?php
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR: " . $e['message'] . " (" . basename($e['file']) . " line " . $e['line'] . ")\n";
    }
});
require __DIR__ . '/includes/config.php';
header('Content-Type: text/plain; charset=utf-8');
$rows = $pdo->query("SELECT c.name_en AS city, m.name_en AS cemetery, m.sect, m.slug FROM cemeteries m JOIN cities c ON c.id = m.city_id")->fetchAll();
foreach ($rows as $r) {
    echo implode(' | ', $r) . "\n";
}
echo count($rows) . " row(s)\n";