<?php
// tester-m9 测试库初始化：logistics_test = install.sql（含 M9 列）
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use support\Db;

$cfg = config('database.connections.mysql');
$host = $cfg['host'] ?? '127.0.0.1';
$port = $cfg['port'] ?? 3306;
$user = $cfg['username'] ?? '';
$pass = $cfg['password'] ?? '';

$dbName = 'logistics_test';
try {
    Db::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "db {$dbName} ready\n";
} catch (Throwable $e) {
    fwrite(STDERR, "create db ERR: " . $e->getMessage() . "\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents(__DIR__ . '/../database/install.sql');
if ($sql === false) {
    fwrite(STDERR, "install.sql read failed\n");
    exit(1);
}
// 按分号切分，保留完整 INSERT 值；跳过纯注释/空语句
$stmtCount = 0;
foreach (explode(";\n", $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    $pdo->exec($stmt);
    $stmtCount++;
}
echo "imported {$stmtCount} statements\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "done\n";
