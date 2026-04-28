<?php
header('Content-Type: text/plain');

$logFile = '/tmp/essl_push_log.txt';

// Detailed logging
file_put_contents($logFile, "\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($logFile, "Request IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);
file_put_contents($logFile, "Request Data: " . print_r($_REQUEST, true) . "\n", FILE_APPEND);

// ==================== BEST CONFIG (Internal) ====================
$host = '45.9.2.198';        // ← CHANGE THIS to your exact MySQL Service Name in Coolify
$port = '3306';
$db   = 'essl_data';
$user = 'root';          // Prefer dedicated user instead of root
$pass = 'root';   // ← Put the real password here

// If you don't remember the service name, use the container name shown in Coolify (e.g. a long random string)

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    file_put_contents($logFile, "✅ DB Connection SUCCESS (Internal)\n", FILE_APPEND);
} 
catch(PDOException $e) {
    file_put_contents($logFile, "❌ DB Connection FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR - DB Connection Failed. Check /tmp/essl_push_log.txt";
    exit;
}

// Insert record
try {
    $stmt = $pdo->prepare("INSERT INTO attendance_logs 
        (machine_sn, user_id, punch_time, verify_type, device_ip, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?)");

    $machine_sn  = $_REQUEST['SN'] ?? 'M160';
    $user_id     = $_REQUEST['PIN'] ?? $_REQUEST['UserID'] ?? $_REQUEST['pin'] ?? null;
    $punch_time  = $_REQUEST['Time'] ?? date('Y-m-d H:i:s');
    $verify_type = $_REQUEST['VerifyType'] ?? 0;

    if (strlen($punch_time) == 14) {
        $punch_time = substr($punch_time,0,4).'-'.substr($punch_time,4,2).'-'.substr($punch_time,6,2).' '.
                      substr($punch_time,8,2).':'.substr($punch_time,10,2).':'.substr($punch_time,12,2);
    }

    $raw_data = json_encode($_REQUEST);

    $stmt->execute([$machine_sn, $user_id, $punch_time, $verify_type, $_SERVER['REMOTE_ADDR'] ?? '', $raw_data]);

    file_put_contents($logFile, "✅ Attendance record inserted successfully\n", FILE_APPEND);
    echo "OK";

} catch(Exception $e) {
    file_put_contents($logFile, "❌ Insert Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR";
}
?>