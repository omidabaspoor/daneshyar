<?php
require_once __DIR__ . '/config.php';

/**
 * اتصال PDO - تک نمونه (singleton)
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (DEV_MODE) {
                die('<div style="font-family:Vazirmatn,Tahoma;direction:rtl;padding:30px;color:#fff;background:#111;border:1px solid #eb7c2a;border-radius:14px;margin:40px">
                    <h2 style="color:#eb7c2a">❌ خطا در اتصال به دیتابیس</h2>
                    <p>'.$e->getMessage().'</p>
                    <p>لطفاً مطمئن شو که در فایل <code>includes/config.php</code> اطلاعات MySQL درست وارد شده و دیتابیس <b>daneshyar</b> ساخته شده است.</p>
                    <p>برای نصب اولیه به آدرس <a href="install.php" style="color:#eb7c2a">install.php</a> برو.</p>
                </div>');
            }
            die('Database connection failed.');
        }
    }
    return $pdo;
}
