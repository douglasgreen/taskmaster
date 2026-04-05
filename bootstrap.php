<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$configFile = __DIR__ . '/config/config.ini';
if (!file_exists($configFile)) {
    throw new RuntimeException('Config file not found. Please create config/config.ini from config.ini.sample');
}

$config = parse_ini_file($configFile, true);
if ($config === false) {
    throw new RuntimeException('Error parsing config file.');
}

$connection = $config['connection'] ?? [];
$host = $connection['host'] ?? '';
$port = (int) ($connection['port'] ?? 3306);
$database = $connection['db'] ?? '';
$user = $connection['user'] ?? '';
$password = $connection['pass'] ?? '';

if ($host === '~' || $database === '~' || $user === '~' || $password === '~' || $host === '' || $database === '' || $user === '' || $password === '') {
    throw new RuntimeException('Config not set up. Please update config.ini');
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
$pdo = new PDO($dsn, $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
    'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

return ['pdo' => $pdo, 'twig' => $twig];
