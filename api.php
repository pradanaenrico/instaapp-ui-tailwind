<?php
require __DIR__ . '/utils.php';
require __DIR__ . '/jwt.php';

$cfg = require __DIR__ . '/config.php';
$pdo = get_db();
ensure_uploads();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  // Preflight
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  exit;
}

$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'],'/') : '';
$segments = $path ? explode('/', $path) : [];

function auth_user(){
  global $cfg;
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] 
       ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
       ?? '';
  // var_dump($hdr); exit;
  if (preg_match('/Bearer\s+(.*)/', $hdr, $m)) {
    $payload = jwt_verify($m[1], $cfg['jwt_secret']);
    if ($payload) return $payload;

    // var_dump($payload); exit;
  }
  return null;
}

function require_auth(){
  $u = auth_user();
  if (!$u) send_json(['error'=>'Unauthorized'], 401);
  return $u;
}


function hydrate_post($row, $user_id=null){
  global $pdo;
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
  $stmt->execute([$row['id']]);
  $likes = (int)$stmt->fetchColumn();

  $liked = false;
  if ($user_id){
    $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$row['id'], $user_id]);
    $liked = (bool)$stmt->fetchColumn();
  }

  $stmt = $pdo->prepare("SELECT c.id, c.text, c.created_at, u.username AS author FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.id DESC");
  $stmt->execute([$row['id']]);
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
  $stmt->execute([$row['user_id']]);
  $author = $stmt->fetchColumn();

  return [
    'id'=>$row['id'],
    'caption'=>$row['caption'],
    'image_url'=>($row['image_path'] ? ($cfg['uploads_dir'].'/'.basename($row['image_path'])) : null),
    'created_at'=>$row['created_at'],
    'author'=>$author,
    'likes'=>$likes,
    'liked'=>$liked,
    'comments'=>$comments,
    'can_edit'=>($user_id && $user_id==$row['user_id'])
  ];
}


$method = $_SERVER['REQUEST_METHOD'];

try {

if ($method==='POST' && $segments==['register']) {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!$data || empty($data['username']) || empty($data['password'])) send_json(['error'=>'username & password required'], 400);
  $stmt=$pdo->prepare("INSERT INTO users(username,password_hash,created_at) VALUES(?,?,?)");
  $stmt->execute([$data['username'], password_hash($data['password'], PASSWORD_DEFAULT), now_iso()]);
  send_json(['message'=>'registered']);
}

if ($method==='POST' && $segments==['login']) {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!$data || empty($data['username']) || empty($data['password'])) send_json(['error'=>'username & password required'], 400);
  $stmt=$pdo->prepare("SELECT * FROM users WHERE username=?");
  $stmt->execute([$data['username']]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$u || !password_verify($data['password'], $u['password_hash'])) send_json(['error'=>'invalid credentials'], 401);
  $token = jwt_sign(['sub'=>$u['id'], 'username'=>$u['username']], $cfg['jwt_secret']);
  send_json(['token'=>$token, 'user'=>['id'=>$u['id'],'username'=>$u['username']]]);
}

if ($method==='GET' && $segments==['me']) {
  $u = require_auth();
  send_json(['user'=>$u]);
}

if ($method==='GET' && $segments==['posts']) {
  $auth = auth_user();
  $uid = $auth['sub'] ?? null;
  $q = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
  $out = array_map(function($r) use ($uid){ return hydrate_post($r, $uid); }, $rows);
  send_json(['posts'=>$out]);
}

if ($method==='POST' && $segments==['posts']) {
  $u = require_auth();
  // Expect multipart/form-data with fields: caption, image (file optional)
  $caption = $_POST['caption'] ?? null;
  $image_path = null;
  if (!empty($_FILES['image']) && $_FILES['image']['error']===UPLOAD_ERR_OK){
    $tmp = $_FILES['image']['tmp_name'];
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $name = uniqid('img_', true) . ($ext ? ('.'.$ext) : '');
    $dest = $cfg['uploads_dir'].'/'.$name;
    if (!move_uploaded_file($tmp, $dest)) send_json(['error'=>'failed to save image'], 500);
    $image_path = $dest;
  }
  $stmt=$pdo->prepare("INSERT INTO posts(user_id, caption, image_path, created_at) VALUES(?,?,?,?)");
  $stmt->execute([$u['sub'], $caption, $image_path, now_iso()]);
  send_json(['message'=>'posted']);
}

if ($method==='DELETE' && count($segments)==2 && $segments[0]==='posts') {
  $u = require_auth();
  $post_id = intval($segments[1]);
  // check ownership
  $stmt = $pdo->prepare("SELECT user_id, image_path FROM posts WHERE id=?");
  $stmt->execute([$post_id]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$p) send_json(['error'=>'not found'], 404);
  if ($p['user_id'] != $u['sub']) send_json(['error'=>'forbidden'], 403);
  $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$post_id]);
  if (!empty($p['image_path']) && file_exists($p['image_path'])) @unlink($p['image_path']);
  send_json(['message'=>'deleted']);
}

if ($method==='POST' && count($segments)==3 && $segments[0]==='posts' && $segments[2]==='like') {
  $u = require_auth();
  $post_id = intval($segments[1]);
  // toggle like
  $stmt = $pdo->prepare("SELECT id FROM likes WHERE post_id=? AND user_id=?");
  $stmt->execute([$post_id, $u['sub']]);
  $like_id = $stmt->fetchColumn();
  if ($like_id){
    $pdo->prepare("DELETE FROM likes WHERE id=?")->execute([$like_id]);
    send_json(['liked'=>false]);
  } else {
    $pdo->prepare("INSERT INTO likes(user_id, post_id, created_at) VALUES(?,?,?)")->execute([$u['sub'],$post_id, now_iso()]);
    send_json(['liked'=>true]);
  }
}

if ($method==='POST' && count($segments)==3 && $segments[0]==='posts' && $segments[2]==='comments') {
  $u = require_auth();
  $post_id = intval($segments[1]);
  $data = json_decode(file_get_contents('php://input'), true);
  if (!$data || empty($data['text'])) send_json(['error'=>'text required'], 400);
  $pdo->prepare("INSERT INTO comments(user_id, post_id, text, created_at) VALUES(?,?,?,?)")->execute([$u['sub'],$post_id,$data['text'], now_iso()]);
  send_json(['message'=>'commented']);
}

send_json(['error'=>'Not Found','path'=>$segments], 404);

} catch (Throwable $e){
  send_json(['error'=>'Server Error','details'=>$e->getMessage()], 500);
}
