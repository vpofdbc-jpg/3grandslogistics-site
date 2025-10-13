<?php
// /place_order.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// must be logged in
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$userId = (int)$_SESSION['user_id'];

/* Per-mile rates */
$RATES = ['car'=>2.20, 'van'=>2.80, 'truck'=>3.50];

/* Inputs (accept either field naming) */
$pickup   = trim($_POST['pickup_address']   ?? '');
$delivery = trim($_POST['delivery_address'] ?? '');
$pkg      = trim($_POST['package_size']     ?? '');
$vehicle  = strtolower(trim($_POST['vehicle'] ?? ($_POST['vehicle_type'] ?? '')));
$miles    = (float)($_POST['miles'] ?? 0);
$notes    = trim($_POST['notes'] ?? '');

/* Basic validation */
if ($pickup==='' || $delivery==='' || $pkg==='' || !isset($RATES[$vehicle]) || $miles<=0) {
  http_response_code(400);
  exit('Bad input.');
}

/* Pricing */
$rate         = $RATES[$vehicle];
$mileagePrice = round($miles * $rate, 2);
$pkgFee       = ['Small'=>0, 'Medium'=>10, 'Large'=>20][$pkg] ?? 0;
$totalNum     = round($mileagePrice + $pkgFee, 2);             // numeric for DB
$totalStr     = number_format($totalNum, 2, '.', '');          // string for payment

/* Insert order with tolerant column mapping */
$conn->begin_transaction();

/* discover columns */
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM orders");
while ($c = $res->fetch_assoc()) $cols[] = $c['Field'];
$res->close();

/* map optional columns if present */
$vehicleCol = in_array('vehicle', $cols, true)       ? 'vehicle'
            : (in_array('vehicle_type', $cols, true) ? 'vehicle_type' : null);
$totalCol   = in_array('total', $cols, true)         ? 'total'
            : (in_array('total_price', $cols, true)  ? 'total_price'  : null);
$milesCol   = in_array('miles', $cols, true)         ? 'miles' : null;
$notesCol   = in_array('notes', $cols, true)         ? 'notes' : null;

$fields = ['user_id','pickup_address','delivery_address','package_size','status','created_at'];
$marks  = ['?','?','?','?','?','NOW()'];
$types  = 'issss';
$vals   = [$userId,$pickup,$delivery,$pkg,'Pending'];

if ($vehicleCol){ $fields[]=$vehicleCol; $marks[]='?'; $types.='s'; $vals[]=$vehicle; }
if ($milesCol)  { $fields[]=$milesCol;   $marks[]='?'; $types.='d'; $vals[]=$miles;   }
if ($totalCol)  { $fields[]=$totalCol;   $marks[]='?'; $types.='d'; $vals[]=$totalNum;}
if ($notesCol)  { $fields[]=$notesCol;   $marks[]='?'; $types.='s'; $vals[]=$notes;   }

$sql = "INSERT INTO orders (" . implode(',', $fields) . ") VALUES (" . implode(',', $marks) . ")";
$st  = $conn->prepare($sql);
$st->bind_param($types, ...$vals);
$st->execute();
$orderId = (int)$conn->insert_id;
$st->close();

$conn->commit();

/* For payment page and redirect */
$_SESSION['pay_order'] = [
  'order_id' => $orderId,
  'amount'   => $totalStr,
  'vehicle'  => $vehicle,
  'miles'    => $miles,
  'pkg'      => $pkg,
];

$qs = http_build_query(['order_id'=>$orderId, 'amount'=>$totalStr]);
header("Location: /payment.php?$qs");
exit;




