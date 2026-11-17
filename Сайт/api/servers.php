<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

/** Проверка SA-MP query protocol. На недоступном сервере не показывает выдуманный онлайн. */
function query_samp(string $host, int $port): ?array
{
    if ($host === '' || $port < 1 || $port > 65535) return null;
    $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $host : gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return null;
    $pieces = array_map('intval', explode('.', $ip));
    $packet = 'SAMP' . pack('C4', ...$pieces) . pack('v', $port) . 'i';
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client('udp://' . $ip . ':' . $port, $errno, $errstr, 0.65, STREAM_CLIENT_CONNECT);
    if (!is_resource($sock)) return null;
    stream_set_timeout($sock, 0, 600000);
    $written = @fwrite($sock, $packet);
    if ($written === false || $written < strlen($packet)) { fclose($sock); return null; }
    $data = @fread($sock, 2048);
    fclose($sock);
    if (!is_string($data) || strlen($data) < 20 || substr($data, 0, 4) !== 'SAMP' || substr($data, 10, 1) !== 'i') return null;
    $players = unpack('v', substr($data, 12, 2))[1];
    $max = unpack('v', substr($data, 14, 2))[1];
    if ($players > 65535 || $max > 65535) return null;
    return ['players'=>$players, 'max_players'=>$max];
}

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
