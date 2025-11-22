<?php
// backfill_meta.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

// path to your log file
$logFile = __DIR__ . '/orders_log.txt';
if (!file_exists($logFile)) {
    exit("Log file not found: $logFile");
}

$handle = fopen($logFile, 'r');
if (!$handle) exit("Could not open log file.");

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') continue;

    // Expecting JSON per line
    $data = json_decode($line, true);
    if (!$data || empty($data['order_id'])) {
        continue;
    }
    $order_id = (int)$data['order_id'];

    // fields to save
    $fields = [
        'pickup', 'delivery', 'pickup_date', 'pickup_time', 'notes',
        'package_type', 'vehicle',
        'miles', 'mileage_cost', 'package_cost', 'final_cost', 'applied'
    ];

    foreach ($fields as $key) {
        if (!empty($data[$key])) {
            // check if already exists
            $check = $conn->prepare("SELECT 1 FROM order_meta WHERE order_id=? AND meta_key=?");
            $check->bind_param('is', $order_id, $key);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                $ins = $conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
                $val = (string)$data[$key];
                $ins->bind_param('iss', $order_id, $key, $val);
                $ins->execute();
                $ins->close();
                echo "Inserted $key for order #$order_id\n";
            }
        }
    }
}

fclose($handle);
$conn->close();

echo "Backfill complete.\n";
