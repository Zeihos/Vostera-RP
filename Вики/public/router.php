<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$full = realpath(__DIR__ . $path);
if ($full !== false && str_starts_with($full, __DIR__ . DIRECTORY_SEPARATOR) && is_file($full) && !preg_match('~\.(?:php\d*|phtml|phar)$~i', $path)) {
    return false;
}
require __DIR__ . '/index.php';
