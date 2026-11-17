<?php
declare(strict_types=1);

// Запускать только из CLI, на сервере или локально до публикации форума.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$private = dirname(__DIR__) . '/private';
$keyPath = $private . '/install.key';
if (!is_dir($private)) {
    fwrite(STDERR, "Папка private не найдена.\n");
    exit(1);
}
if (is_file($private . '/store.json')) {
    fwrite(STDERR, "Форум уже установлен; нельзя создавать новый установочный ключ.\n");
    exit(1);
}
$rotate = in_array('--rotate', $argv, true);
if (file_exists($keyPath) && !$rotate) {
    fwrite(STDERR, "Ключ уже существует. Для замены компрометированного ключа: php tools/prepare-install.php --rotate\n");
    exit(1);
}

$key = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
if (!$rotate || !is_file($keyPath)) {
    $fp = @fopen($keyPath, 'x');
    if ($fp === false) {
        fwrite(STDERR, "Не удалось безопасно создать ключ. Проверьте права доступа.\n");
        exit(1);
    }
    fwrite($fp, $key . "\n");
    fclose($fp);
} else {
    $tmp = tempnam($private, 'install_');
    if ($tmp === false || file_put_contents($tmp, $key . "\n", LOCK_EX) === false) {
        fwrite(STDERR, "Не удалось заменить установочный ключ.\n");
        exit(1);
    }
    @chmod($tmp, 0600);
    if (!rename($tmp, $keyPath)) {
        @unlink($tmp);
        fwrite(STDERR, "Не удалось заменить установочный ключ.\n");
        exit(1);
    }
}
@chmod($keyPath, 0600);
echo "Новый ключ установки форума (показывается один раз):\n{$key}\n";
echo "Не публикуйте его в GitHub, не отправляйте в чат и удалите после установки.\n";
