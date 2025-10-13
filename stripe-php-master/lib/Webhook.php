<?php
declare(strict_types=1);
require __DIR__.'/../db.php'; require __DIR__.'/../customer/stripe_config.php';
$payload=file_get_contents('php://input'); $evt=json_decode($payload,true) ?: [];
$type=$evt['type'] ?? ''; $data=$evt['data']['object'] ?? [];

function u(mysqli $c,int $uid,array $kv){ // tiny updater
  $parts=[]; $types=''; $args=[];
  foreach($kv as $k=>$v){ $parts[]="$k=?"; $types.='s'; $args[]=(string)$v; }
  $types.='i'; $args[]=$uid;
  $q=$c->prepare("UPDATE users SET ".implode(',',$parts)." WHERE id=?"); $q->bind_param($types, ...$args); $q->execute(); $q->close();
}

switch($type){
  case 'checkout.session.completed':
    // store customer/subscription on user
    $uid=(int)($data['client_reference_id'] ?? 0);
    if($uid>0){
      u($conn,$uid,[
        'stripe_customer_id'=>$data['customer'] ?? '',
        'subscription_id'=>$data['subscription'] ?? '',
        'subscription_status'=>'active'
      ]);
    }
    break;

  case 'invoice.payment_succeeded':
    // new cycle paid => reset counters
    $uid=(int)(($data['lines']['data'][0]['metadata']['app_user_id'] ?? $data['metadata']['app_user_id'] ?? 0));
    if($uid>0){ u($conn,$uid,['subscription_status'=>'active','deliveries_in_cycle'=>'0','cycle_anchor'=>date('Y-m-d H:i:s')]); }
    break;

  case 'customer.subscription.updated':
  case 'customer.subscription.deleted':
    $sub=$data; $status=(string)($sub['status'] ?? 'canceled');
    $sc=(string)($sub['customer'] ?? '');
    if($sc!==''){
      $q=$conn->prepare("UPDATE users SET subscription_status=? , subscription_id=? WHERE stripe_customer_id=?");
      $sid=(string)($sub['id'] ?? ''); $q->bind_param('sss',$status,$sid,$sc); $q->execute(); $q->close();
    }
    break;
}
http_response_code(200);


