<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

header('Content-Type: application/json');

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$viewer = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper = ($viewer['ruolo'] ?? 'operator') === 'superuser';

if (!$isSuper) {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'detail'=>'Not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$data['csrf'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'detail'=>'Bad CSRF']);
  exit;
}

$room_id     = (int)($data['room_id'] ?? 0);
$customer_id = (int)($data['customer_id'] ?? 0);
$device_id   = (int)($data['device_id'] ?? 0);
$x_pct       = (float)($data['x_pct'] ?? -1);
$y_pct       = (float)($data['y_pct'] ?? -1);

if ($room_id<=0 || $customer_id<=0 || $device_id<=0 || $x_pct<0 || $y_pct<0 || $x_pct>100 || $y_pct>100) {
  http_response_code(422);
  echo json_encode(['ok'=>false, 'detail'=>'Invalid payload']);
  exit;
}

try {
  $pdo = $GLOBALS['pdo'] ?? null;
  if (!$pdo instanceof PDO) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : 'ardisafe';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn  = defined('DB_DSN')  ? DB_DSN  : "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo  = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }

  // verifica ownership device → room+customer
  $st = $pdo->prepare('SELECT 1 FROM device WHERE id=? AND room_id=? AND customer_id=? LIMIT 1');
  $st->execute([$device_id, $room_id, $customer_id]);
  if (!$st->fetch()) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'detail'=>'Device not found in room/customer']);
    exit;
  }

  // esiste già una posizione?
  $st = $pdo->prepare('SELECT id FROM device_position WHERE device_id=? AND room_id=? AND customer_id=? LIMIT 1');
  $st->execute([$device_id, $room_id, $customer_id]);
  $pos = $st->fetch();

  if ($pos) {
    $st = $pdo->prepare('UPDATE device_position SET x_pct=?, y_pct=?, updated_at=NOW() WHERE id=?');
    $st->execute([round($x_pct,2), round($y_pct,2), $pos['id']]);
  } else {
    $st = $pdo->prepare('INSERT INTO device_position (room_id, device_id, customer_id, x_pct, y_pct, created_at) VALUES (?,?,?,?,?,NOW())');
    $st->execute([$room_id, $device_id, $customer_id, round($x_pct,2), round($y_pct,2)]);
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'detail'=>'DB error']);
}
