<?php
declare(strict_types=1);

// Запускать только из CLI, на сервере или локально до публикации форума.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$private = dirname(__DIR__) . '/private';
$keyPath = $private . '/install.key';
$appKeyPath = $private . '/app.key';
$dataPath = $private . '/data.json';
$examplePath = $private . '/data.example.json';
if (!is_dir($private)) {
    fwrite(STDERR, "Папка private не найдена.\n");
    exit(1);
}
if (is_file($dataPath)) {
    $state = json_decode((string)file_get_contents($dataPath), true);
    if (is_array($state) && !empty($state['installed'])) {
        if (!in_array('--migrate', $argv, true)) {
            fwrite(STDERR, "Wiki уже установлена. Для безопасного обновления выполните: php tools/prepare-install.php --migrate\n");
            exit(1);
        }
        if (!is_file($appKeyPath)) {
            $app = @fopen($appKeyPath, 'x');
            if ($app === false) {
                fwrite(STDERR, "Не удалось создать отдельный секрет для входа.\n");
                exit(1);
            }
            fwrite($app, bin2hex(random_bytes(32)) . "\n");
            fclose($app);
            @chmod($appKeyPath, 0600);
        }
        @unlink($keyPath);
        echo "Секрет входа Wiki подготовлен. Прежний установочный ключ удалён.\n";
        exit(0);
    }
}
if (!is_file($dataPath)) {
    if (!is_file($examplePath) || !copy($examplePath, $dataPath)) {
        fwrite(STDERR, "Не удалось создать private/data.json из шаблона.\n");
        exit(1);
    }
    @chmod($dataPath, 0600);
}
if (!is_file($appKeyPath)) {
    $app = @fopen($appKeyPath, 'x');
    if ($app === false) {
        fwrite(STDERR, "Не удалось создать секрет входа Wiki.\n");
        exit(1);
    }
    fwrite($app, bin2hex(random_bytes(32)) . "\n");
    fclose($app);
    @chmod($appKeyPath, 0600);
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
echo "Новый ключ установки Wiki (показывается один раз):\n{$key}\n";
echo "Не публикуйте его в GitHub, не отправляйте в чат и удалите после установки.\n";
