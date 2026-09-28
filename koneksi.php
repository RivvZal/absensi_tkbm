<?php

date_default_timezone_set('Asia/Jakarta');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "192.168.1.225";
$db   = "absen_tkbm";
$user = "TKBM";
$pass = "ANAKBANDEL";

if ($pass === false) {
    $pass = '';
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die(
        'Koneksi database gagal. Pastikan MySQL aktif, database "' .
        htmlspecialchars($db, ENT_QUOTES, 'UTF-8') .
        '" sudah diimpor, dan konfigurasi di koneksi.php sudah benar.'
    );
}
