<?php
function b64url($data){ return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function b64url_decode($data){ return base64_decode(strtr($data, '-_', '+/')); }

function jwt_sign($payload, $secret, $exp_sec=3600*24*7){
  $header = ['alg'=>'HS256','typ'=>'JWT'];
  $payload['exp'] = time() + $exp_sec;
  $segments = [ b64url(json_encode($header)), b64url(json_encode($payload)) ];
  $signing_input = implode('.', $segments);
  $signature = hash_hmac('sha256', $signing_input, $secret, true);
  $segments[] = b64url($signature);
  return implode('.', $segments);
}

function jwt_verify($jwt, $secret){
  $parts = explode('.', $jwt);
  if(count($parts)!==3) return null;
  list($h, $p, $s) = $parts;
  $sig = b64url_decode($s);
  $check = hash_hmac('sha256', "$h.$p", $secret, true);
  if(!hash_equals($check, $sig)) return null;
  $payload = json_decode(b64url_decode($p), true);
  if(!$payload) return null;
  if(isset($payload['exp']) && time() > $payload['exp']) return null;
  return $payload;
}
