<?php
require_once __DIR__ . '/../db_connect.php';
$pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW()");
echo "Cleaned " . $pdo->rowCount() . " expired OTPs\n";
?>