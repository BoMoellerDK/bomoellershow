<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$public_file = dirname(__DIR__) . '/public_html' . $path;

if ($path !== '/' && is_file($public_file)) {
    return false;
}

require dirname(__DIR__) . '/public_html/index.php';
