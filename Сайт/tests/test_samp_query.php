<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/samp_query.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function info(string $header, int $players = 37, int $max = 150): string
{
    $result = $header . "\x00" . pack('v', $players) . pack('v', $max);
    foreach (['VOSTERA RP', 'RolePlay', 'Russian'] as $field) {
        $result .= pack('V', strlen($field)) . $field;
    }
    return $result;
}

$header = 'SAMP' . pack('C4', 127, 0, 0, 1) . pack('v', 7777) . 'i';
$valid = info($header);
verify(samp_parse_info($valid, $header) === ['players' => 37, 'max_players' => 150], 'Корректный ответ');
verify(samp_parse_info(info($header, 0, 150), $header)['players'] === 0, 'Пустой сервер');
verify(samp_parse_info(info($header, 151, 150), $header) === null, 'Онлайн больше лимита');
verify(samp_parse_info(info($header, 1, 0), $header) === null, 'Нулевой лимит');
verify(samp_parse_info(info($header . 'x'), $header) === null, 'Неверная длина заголовка');
verify(samp_parse_info(info(substr_replace($header, 'z', 10, 1)), $header) === null, 'Не тот opcode');
verify(samp_parse_info(info(substr_replace($header, "\x00", 8, 1)), $header) === null, 'Не тот порт');
verify(samp_parse_info(substr_replace($valid, "\x02", 11, 1), $header) === null, 'Недопустимый флаг пароля');
verify(samp_parse_info(substr($valid, 0, -1), $header) === null, 'Обрезанная строка языка');
verify(samp_parse_info(substr_replace($valid, pack('V', 1000), 16, 4), $header) === null, 'Длина строки вне пакета');
verify(query_samp('', 7777) === null && query_samp('localhost', 0) === null, 'Неверный адрес/порт');

echo "OK: SA-MP query: 11 проверок\n";
