<?php
declare(strict_types=1);

/**
 * drop_all_tables.php
 * حذف كل الجداول في قاعدة البيانات hcsfcsto_toro
 * ⚠️ تأكد أنك في قاعدة البيانات الصحيحة، لا تستعمل هذا في الإنتاج بدون نسخ احتياطية
 */

// إعدادات الاتصال
$dbHost = 'sv61.ifastnet10.org';
$dbUser = 'hcsfcsto_user';
$dbPass = 'Mohd28332@';
$dbName = 'hcsfcsto_toro';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "Connected to database `$dbName` successfully.\n";

    // استعلام لجلب كل الجداول
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        echo "No tables found.\n";
        exit;
    }

    echo "Found " . count($tables) . " tables.\n";

    // تعطيل القيود مؤقتاً
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table: $table\n";
    }

    // إعادة تفعيل القيود
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "All tables dropped successfully.\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}