<?php
function send_json($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json');
  // CORS
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  echo json_encode($data);
  exit;
}
function get_db() {
  $cfg = require __DIR__ . '/config.php';
  $pdo = new PDO('sqlite:' . $cfg['db_path']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA foreign_keys = ON;');
  return $pdo;
}
function ensure_uploads(){
  $cfg = require __DIR__ . '/config.php';
  if (!is_dir($cfg['uploads_dir'])) { mkdir($cfg['uploads_dir'], 0777, true); }
}
function now_iso(){
  return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
}
