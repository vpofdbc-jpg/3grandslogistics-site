<?php
// /customer/rate_card.php — Customer-facing rate card
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($conn) && $conn instanceof mysqli) $conn->set_charset('utf8mb4');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Try DB-driven rate card first; otherwise use the inline fallback below.
   Optional table (if you choose to add it later):
   CREATE TABLE rate_card(
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(64) NOT NULL,
     vehicle VARCHAR(32) NOT NULL,             -- bike, car, suv, van, box_truck, etc.
     base_fee DECIMAL(10,2) NOT NULL,
     per_mile DECIMAL(10,2) NOT NULL DEFAULT 0,
     per_lb DECIMAL(10,2) NOT NULL DEFAULT 0,  -- weight fee (optional)
     min_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
     extra_json JSON NULL,                      -- {"after_hours_pct":15,"extra_stop":5,"stair_carry":10}
     active TINYINT(1) NOT NULL DEFAULT 1
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
$rows=[];
try{
  $q=$conn->query("SHOW TABLES LIKE 'rate_card'");
  if ($q && $q->num_rows){ $q->close();
    $r=$conn->query("SELECT name,vehicle,base_fee,per_mile,per_lb,min_charge,COALESCE(extra_json,'{}') extra_json
                     FROM rate_card WHERE active=1 ORDER BY id ASC");
    $rows=$r?$r->fetch_all(MYSQLI_ASSOC):[];
  }
}catch(Throwable $e){}

/* Fallback rate card (edit to match your business) */
if(!$rows){
  $rows=[
    ['name'=>'Bike Courier','vehicle'=>'bike','base_fee'=>10,'per_mile'=>1.25,'per_lb'=>0,'min_charge'=>10,'extra_json'=>json_encode(['after_hours_pct'=>15,'extra_stop'=>4])],
    ['name'=>'Car','vehicle'=>'car','base_fee'=>15,'per_mile'=>1.75,'per_lb'=>0,'min_charge'=>15,'extra_json'=>json_encode(['after_hours_pct'=>15,'extra_stop'=>5])],
    ['name'=>'SUV','vehicle'=>'suv','base_fee'=>20,'per_mile'=>2.10,'per_lb'=>0,'min_charge'=>20,'extra_json'=>json_encode(['after_hours_pct'=>15,'extra_stop'=>7])],
    ['name'=>'Cargo Van','vehicle'=>'van','base_fee'=>35,'per_mile'=>2.60,'per_lb'=>0.10,'min_charge'=>35,'extra_json'=>json_encode(['after_hours_pct'=>20,'extra_stop'=>10,'stair_carry'=>15])],
    ['name'=>'Box Truck','vehicle'=>'box_truck','base_fee'=>75,'per_mile'=>3.25,'per_lb'=>0.15,'min_charge'=>75,'extra_json'=>json_encode(['after_hours_pct'=>25,'extra_stop'=>15,'liftgate'=>20])]
  ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rate Card</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto;margin:0;background:#f6f7fb}
  .wrap{max-width:980px;margin:28px auto;padding:0 16px}
  h1{margin:0 0 12px}
  .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:16px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #eef1f6;text-align:left}
  th{background:#fafbfe}
  .small{color:#666;font-size:13px;margin-top:10px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Rate Card</h1>
  <div class="card">
    <table>
      <tr>
        <th>Service</th>
        <th>Vehicle</th>
        <th>Base Fee</th>
        <th>Per-Mile</th>
        <th>Per-Lb (if applies)</th>
        <th>Minimum</th>
        <th>Common Surcharges</th>
      </tr>
      <?php foreach($rows as $r):
        $extra=json_decode($r['extra_json']??'{}',true) ?: [];
        $extras=[];
        foreach($extra as $k=>$v){
          $label=str_replace('_',' ',ucfirst($k));
          $extras[] = ($k==='after_hours_pct') ? "After-hours +".(float)$v."%" : "$label $".number_format((float)$v,2);
        }
      ?>
      <tr>
        <td><?=h($r['name'])?></td>
        <td><?=h($r['vehicle'])?></td>
        <td>$<?=number_format((float)$r['base_fee'],2)?></td>
        <td>$<?=number_format((float)$r['per_mile'],2)?> /mi</td>
        <td><?=((float)$r['per_lb']>0)?'$'.number_format((float)$r['per_lb'],2).' /lb':'—'?></td>
        <td>$<?=number_format((float)$r['min_charge'],2)?></td>
        <td><?= $extras? h(implode(', ', $extras)) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="small">
      Estimates shown at checkout use these rates. Tolls, parking, wait-time, access constraints, or
      <strong>Custom Tasks</strong> may require a manual quote.
    </div>
  </div>
</div>
</body>
</html>
