<?php
$host = 'db-tt'; 
$db   = 'thanhvhv';
$user = 'root';
$pass = '151120';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'" 
];

// 👉 TĂNG THỜI GIAN CHỜ LÊN 60 GIÂY (20 lần x 3 giây)
$max_retries = 20; 
$retry_delay = 3; 
$pdo = null;

for ($i = 0; $i < $max_retries; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        break; 
    } catch (\PDOException $e) {
        if ($i === $max_retries - 1) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
        sleep($retry_delay);
    }
}
?>