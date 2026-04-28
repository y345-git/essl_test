<?php
header('Content-Type: text/plain');

$logFile = '/tmp/essl_push_log.txt';

file_put_contents($logFile, "\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($logFile, "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);

$requestData = $_REQUEST;
$rawBody = file_get_contents("php://input");

if (!empty($rawBody)) {
    file_put_contents($logFile, "RAW BODY RECEIVED (" . strlen($rawBody) . " bytes)\n", FILE_APPEND);
}

// MySQL Connection
$host = '45.9.2.198';     // Change if your MySQL service name is different
$port = '3306';
$db   = 'essl_data';
$user = 'root';
$pass = 'root';           // ← Change this

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    file_put_contents($logFile, "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR";
    exit;
}

// Extract key fields (best effort)
$machine_sn  = $requestData['SN'] ?? 'M160';
$user_id     = $requestData['PIN'] ?? $requestData['UserID'] ?? $requestData['pin'] ?? null;

$punch_time_str = $requestData['Time'] ?? date('Y-m-d H:i:s');

// Convert 14-digit time and set to IST
if (strlen($punch_time_str) == 14) {
    $punch_time_str = substr($punch_time_str,0,4).'-'.substr($punch_time_str,4,2).'-'.substr($punch_time_str,6,2).' '.
                      substr($punch_time_str,8,2).':'.substr($punch_time_str,10,2).':'.substr($punch_time_str,12,2);
}

$dt = new DateTime($punch_time_str ?? 'now', new DateTimeZone('UTC'));
$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
$punch_time_ist = $dt->format('Y-m-d H:i:s');

$verify_type = $requestData['VerifyType'] ?? $requestData['verifymode'] ?? 0;
$status      = $requestData['Status'] ?? $requestData['InOutStatus'] ?? 0;

// Store everything as raw JSON
$raw_request = json_encode($requestData);
$raw_body    = !empty($rawBody) ? json_encode(['content' => $rawBody]) : null;

try {
    $stmt = $pdo->prepare("INSERT INTO attendance_raw_logs 
        (machine_sn, user_id, punch_time, verify_type, status, device_ip, raw_request, raw_body) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $machine_sn,
        $user_id,
        $punch_time_ist,
        $verify_type,
        $status,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $raw_request,
        $raw_body
    ]);

    file_put_contents($logFile, "✅ SAVED | UserID: $user_id | Time(IST): $punch_time_ist\n", FILE_APPEND);
    echo "OK";

} catch(Exception $e) {
    file_put_contents($logFile, "Insert Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "ERROR";
}
?>