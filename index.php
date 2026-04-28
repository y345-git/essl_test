<?php
// essl_push.php - For Coolify

header('Content-Type: text/plain');

// Log all incoming requests (very useful for debugging)
file_put_contents('essl_push_log.txt', 
    date('Y-m-d H:i:s') . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n" .
    print_r($_REQUEST, true) . "\n----------------------------------------\n", 
    FILE_APPEND);

// MySQL Connection using Coolify internal service name
$host = '45.9.2.198';                    // Use the exact service name you gave to MySQL
$port = '3306';
$db   = 'essl_data';
$user = 'root';
$pass = 'root';     // ← Change this

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    file_put_contents('essl_push_log.txt', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR";
    exit;
}

// Insert attendance record
try {
    $stmt = $pdo->prepare("INSERT INTO attendance_logs 
        (machine_sn, user_id, punch_time, verify_type, device_ip, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?)");

    $machine_sn  = $_REQUEST['SN'] ?? 'M160';
    $user_id     = $_REQUEST['PIN'] ?? $_REQUEST['UserID'] ?? $_REQUEST['pin'] ?? null;
    $punch_time  = $_REQUEST['Time'] ?? date('Y-m-d H:i:s');
    $verify_type = $_REQUEST['VerifyType'] ?? 0;
    $device_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

    // Format time if device sends 14-digit string
    if (strlen($punch_time) == 14) {
        $punch_time = substr($punch_time,0,4).'-'.substr($punch_time,4,2).'-'.substr($punch_time,6,2).' '.
                      substr($punch_time,8,2).':'.substr($punch_time,10,2).':'.substr($punch_time,12,2);
    }

    $raw_data = json_encode($_REQUEST);

    $stmt->execute([$machine_sn, $user_id, $punch_time, $verify_type, $device_ip, $raw_data]);

    echo "OK";

} catch(Exception $e) {
    file_put_contents('essl_push_log.txt', "Insert Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR";
}
?>