<?php
const STRIPE_SECRET   = ';   // same as checkout.php
const STRIPE_PUBLISH  = 'pk_test_...';
function stripe_request(string $method, string $path, array $params=[]){
  $url="https://api.stripe.com/v1".$path;
  if ($method==='get' && $params) $url.=(strpos($url,'?')!==false?'&':'?').http_build_query($params);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer ".STRIPE_SECRET], CURLOPT_CUSTOMREQUEST=>strtoupper($method)]);
  if($method!=='get') curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($params));
  $res=curl_exec($ch); if($res===false){ http_response_code(502); exit('Stripe error'); }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode($res,true); if($code>=400){ http_response_code($code); exit($j['error']['message'] ?? 'Stripe failed'); }
  return $j;
}
