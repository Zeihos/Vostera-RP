<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require VOSTERA_ROOT . '/includes/samp_query.php';

$items = [];
foreach ((array)(site()['servers'] ?? []) as $server) {
    if (!is_array($server)) continue;
    $name = trim((string)($server['name'] ?? 'VOSTERA SERVER'));
    $host = trim((string)($server['host'] ?? ''));
    $port = (int)($server['port'] ?? 7777);
    if ($host === '' || $port < 1 || $port > 65535) continue;
    $status = query_samp($host, $port);
    $items[] = [
        'name' => $name,
        'address' => $host . ':' . $port,
        'status' => $status === null ? 'offline' : 'online',
        'players' => $status['players'] ?? null,
        'max_players' => $status['max_players'] ?? null,
    ];
}
echo json_encode(['servers'=>$items,'updated_at'=>gmdate('c')], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
