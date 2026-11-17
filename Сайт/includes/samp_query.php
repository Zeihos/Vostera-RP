<?php
declare(strict_types=1);

/**
 * Парсер ответа SA-MP/open.mp на UDP-запрос i.
 * Протокол: https://open.mp/docs/tutorials/QueryMechanism
 * Возвращает null при повреждённом или не относящемся к запросу пакете.
 */
function samp_parse_info(string $data, string $request): ?array
{
    if (strlen($request) !== 11 || strlen($data) < 20 || substr($data, 0, 11) !== $request) {
        return null;
    }
    $password = ord($data[11]);
    if ($password !== 0 && $password !== 1) {
        return null;
    }
    $players = unpack('v', substr($data, 12, 2))[1];
    $maximum = unpack('v', substr($data, 14, 2))[1];
    if ($maximum < 1 || $players > $maximum) {
        return null;
    }

    // После счётчиков идут три строки (hostname, gamemode, language),
    // каждая предваряется 32-битной длиной в little-endian.
    $offset = 16;
    foreach (range(1, 3) as $_) {
        if (strlen($data) - $offset < 4) {
            return null;
        }
        $length = unpack('V', substr($data, $offset, 4))[1];
        $offset += 4;
        if ($length > strlen($data) - $offset) {
            return null;
        }
        $offset += $length;
    }
    return ['players' => $players, 'max_players' => $maximum];
}

/** Запрашивает онлайн по UDP; нет ответа -> null, не выдумываем игроков. */
function query_samp(string $host, int $port): ?array
{
    if ($host === '' || $port < 1 || $port > 65535) {
        return null;
    }
    $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $host : gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    $octets = array_map('intval', explode('.', $ip));
    $request = 'SAMP' . pack('C4', ...$octets) . pack('v', $port) . 'i';
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client('udp://' . $ip . ':' . $port, $errno, $errstr, 0.65, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return null;
    }
    stream_set_timeout($socket, 0, 600000);
    $written = @fwrite($socket, $request);
    if ($written === false || $written !== strlen($request)) {
        fclose($socket);
        return null;
    }
    $response = @fread($socket, 2048);
    fclose($socket);
    return is_string($response) ? samp_parse_info($response, $request) : null;
}
