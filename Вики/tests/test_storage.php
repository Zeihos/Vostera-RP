<?php
declare(strict_types=1);

/**
 * Тесты Wiki-хранилища в отдельном временном каталоге.
 * Никогда не затрагивает настоящие статьи и пользователей проекта.
 */
function ensure(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$dir = sys_get_temp_dir() . '/vostera-wiki-test-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) throw new RuntimeException('Не удалось создать каталог теста');
$source = dirname(__DIR__) . '/private/lib.php';
copy($source, $dir . '/lib.php');
file_put_contents($dir . '/data.json', "{\"count\":0,\"users\":[]}\n");
$worker = $dir . '/worker.php';
file_put_contents($worker, '<?php require __DIR__ . "/lib.php"; for ($i=0;$i<25;$i++) db_change(function (&$data) { ++$data["count"]; });');
try {
    require $dir . '/lib.php';
    ensure(db_read()['count'] === 0, 'Начальное чтение');
    $result = db_change(function (&$data) { $data['count'] = 3; return 'saved'; });
    ensure($result === 'saved' && db_read()['count'] === 3, 'Запись и чтение');

    $before = file_get_contents($dir . '/data.json');
    try {
        db_change(function (&$data) { $data['count'] = 999; throw new RuntimeException('intentional'); });
        throw new RuntimeException('Не было исключения callback');
    } catch (RuntimeException $e) {
        ensure($e->getMessage() === 'intentional', 'Исключение callback');
    }
    ensure(file_get_contents($dir . '/data.json') === $before, 'База не должна меняться после исключения');
    ensure(glob($dir . '/wiki_*') === [], 'Не осталось временных файлов');

    $processes = [];
    for ($i = 0; $i < 4; $i++) {
        $process = proc_open([PHP_BINARY, $worker], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $dir);
        ensure(is_resource($process), 'Не запустился параллельный обработчик');
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        ensure(proc_close($process) === 0, 'Ошибка параллельной записи: ' . $stderr . $stdout);
    }
    ensure(db_read()['count'] === 103, 'Потеря записей при конкурентных запросах');
    ensure(glob($dir . '/wiki_*') === [], 'Остались временные файлы');
    echo "OK: Wiki JSON: чтение, запись, откат при исключении, 100 параллельных обновлений\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) if(is_file($file)) @unlink($file);
    @rmdir($dir);
}
