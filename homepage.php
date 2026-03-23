<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** Helpers */
function current_customer_id(): int {
  if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  $email = $_SESSION['user']['email'] ?? null; if (!$email) return 0;

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

  $st = $pdo->prepare('SELECT id, ruolo, email FROM customer WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $row = $st->fetch();
  if ($row && !empty($row['id'])) {
    $_SESSION['user']['id']    = (int)$row['id'];
    $_SESSION['user']['ruolo'] = $row['ruolo'] ?? ($_SESSION['user']['ruolo'] ?? 'operator');
    $_SESSION['user']['email'] = $row['email'] ?? $email;
    return (int)$row['id'];
  }
  return 0;
}

/** PDO */
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

/** Helpers DB */
function table_exists(PDO $pdo, string $table): bool {
  try { $pdo->query("SELECT 1 FROM `$table` LIMIT 1"); return true; } catch(Throwable $e){ return false; }
}

/** Repos */
$roomsRepo   = new IotRooms();
$devicesRepo = new IotDevices();
$alarmsRepo  = class_exists('IotRoomAlarms') ? new IotRoomAlarms() : null;

/** Dati utente */
$customerId = current_customer_id();
if ($customerId <= 0) { header('Location: /Ardisafe2.0/login.php'); exit; }
$user   = $_SESSION['user'] ?? [];
$userId = (int)($user['id'] ?? $customerId);

/** Carica layout homepage */
$st = $pdo->prepare("SELECT widgets_json FROM user_homepage WHERE customer_id=? AND user_id=? LIMIT 1");
$st->execute([$customerId,$userId]);
$row = $st->fetch();
$layout = [];
if ($row && !empty($row['widgets_json'])) {
  $layout = json_decode($row['widgets_json'], true);
  if (!is_array($layout)) $layout = [];
}
if (!$layout) {
  // fallback “vuoto”: evita pagina bianca
  $layout = [
    ['id'=>uniqid('w_'), 'type'=>'alarms_recent',   'props'=>['title'=>'Allarmi recenti','limit'=>10,'hours'=>24]],
    ['id'=>uniqid('w_'), 'type'=>'devices_offline', 'props'=>['title'=>'Dispositivi offline','limit'=>10,'offline_threshold_min'=>15]],
  ];
}

/** CSS piccolo e pulito */
echo app()->open('Homepage');
echo '<style>.clcard{margin-bottom:20px}.badge{display:inline-block;padding:.18rem .45rem;border-radius:999px;font-size:12px;font-weight:700;border:1px solid rgba(0,0,0,.08);background:#f3f4f6}.pill{display:inline-block;border-radius:999px;padding:.2rem .5rem;font-size:12px;font-weight:800}.pill--armed{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}.pill--disarmed{background:#eef2ff;color:#1d4ed8;border:1px solid #c7d2fe}.pill--triggered{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}</style>';

/** Dataset condivisi (per evitare query duplicate) */
$allDevices = [];
try { $allDevices = $devicesRepo->listAll($customerId, '', 5000, 0, 'last_seen DESC'); } catch (Throwable $e) {}
$rooms = [];
try { $rooms = $roomsRepo->listByCustomer($customerId, '', 200, 0, 'name ASC'); } catch (Throwable $e) {}
$roomIds = array_map(fn($r)=> (int)$r->getId(), $rooms);
$alarmMap = [];
if ($alarmsRepo && $roomIds) {
  try { $alarmMap = $alarmsRepo->getStatesByRoomIds($roomIds, $customerId); } catch (Throwable $e) {}
}

/** Renderers */
function w_alarms_recent(PDO $pdo, int $customerId, array $props): string {
  $limit = max(1, (int)($props['limit'] ?? 10));
  $hours = max(1, (int)($props['hours'] ?? 24));

  $rows = [];
  try {
    if (table_exists($pdo,'alarm_event')) {
      $st = $pdo->prepare("SELECT id, room_id, device_id, note, image_url, created_at FROM alarm_event WHERE customer_id=? AND created_at >= (NOW() - INTERVAL {$hours} HOUR) ORDER BY created_at DESC LIMIT {$limit}");
      $st->execute([$customerId]); $rows = $st->fetchAll();
    } elseif (table_exists($pdo,'alert_event')) {
      $st = $pdo->prepare("SELECT id, room_id, device_id, note, image_url, captured_at AS created_at FROM alert_event WHERE customer_id=? AND captured_at >= (NOW() - INTERVAL {$hours} HOUR) ORDER BY captured_at DESC LIMIT {$limit}");
      $st->execute([$customerId]); $rows = $st->fetchAll();
    } elseif (table_exists($pdo,'event')) {
      $st = $pdo->prepare("SELECT id, room_id, device_id, payload AS note, created_at FROM event WHERE customer_id=? AND created_at >= (NOW() - INTERVAL {$hours} HOUR) ORDER BY created_at DESC LIMIT {$limit}");
      $st->execute([$customerId]); $rows = $st->fetchAll();
    }
  } catch (Throwable $e) {}

  $t = (new CLTable())->start()->theme('boxed');
  $t->header(['Data','Stanza/Device','Nota','Immagine']); $t->rawCols([3]);
  if (!$rows) $t->row(['—','—','—','—']);
  foreach ($rows as $r) {
    $dt = htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? 'now')), ENT_QUOTES,'UTF-8');
    $sd = 'Room #'.(int)($r['room_id'] ?? 0);
    if (!empty($r['device_id'])) $sd .= ' / Dev #'.(int)$r['device_id'];
    $note = htmlspecialchars((string)($r['note'] ?? ''), ENT_QUOTES,'UTF-8');
    $img  = !empty($r['image_url']) ? '<a href="'.htmlspecialchars($r['image_url'],ENT_QUOTES,'UTF-8').'" target="_blank">🖼️</a>' : '—';
    $t->row([$dt,$sd,$note,$img]);
  }
  $title = htmlspecialchars($props['title'] ?? 'Allarmi recenti',ENT_QUOTES,'UTF-8');
  return (new CLCard())->start()->header($title)->body($t->render(), true)->render();
}

function w_devices_offline(array $allDevices, array $props): string {
  $limit = max(1, (int)($props['limit'] ?? 10));
  $thr   = max(1, (int)($props['offline_threshold_min'] ?? 15));
  $now   = time();
  $rows = [];
  foreach ($allDevices as $d) {
    /** @var IotDevice $d */
    $st = strtolower($d->getStatus() ?? 'unknown');
    $ls = $d->getLastSeen() ? strtotime($d->getLastSeen()) : null;
    $isOld = $ls ? (($now - $ls) > ($thr*60)) : true;
    if ($st === 'offline' || ($st !== 'online' && $isOld)) $rows[] = $d;
    if (count($rows) >= $limit) break;
  }
  $t = (new CLTable())->start()->theme('boxed');
  $t->header(['Nome','Stanza','Stato','Ultimo visto','Azioni']); $t->rawCols([2,4]);
  if (!$rows) $t->row(['—','—','—','—','—']);
  foreach ($rows as $d) {
    $name = htmlspecialchars($d->getName(),ENT_QUOTES,'UTF-8');
    $room = (int)$d->getRoomId(); $room = $room ? 'Stanza #'.$room : '—';
    $st   = '<span class="badge">'.htmlspecialchars($d->getStatus() ?? 'unknown',ENT_QUOTES).'</span>';
    $seen = $d->getLastSeen() ? date('d/m/Y H:i', strtotime($d->getLastSeen())) : '—';
    $act  = (new CLButton())->startGroup(['merge'=>true])->link('👁️','/Ardisafe2.0/device_view.php?id='.(int)$d->getId(),['variant'=>'secondary'])->render();
    $t->row([$name,$room,$st,$seen,$act]);
  }
  $title = htmlspecialchars($props['title'] ?? 'Dispositivi offline',ENT_QUOTES,'UTF-8');
  return (new CLCard())->start()->header($title)->body($t->render(), true)->render();
}

function w_rooms_overview(array $rooms, array $alarmMap, array $allDevices, array $props): string {
  // ---- props ----
  $limit     = max(1, (int)($props['limit'] ?? 12));
  $orderBy   = (($props['order_by'] ?? 'name') === 'device_count') ? 'device_count' : 'name';

  // ---- chi può vedere Modifica/Cancella ----
  $isSuper = (($_SESSION['user']['ruolo'] ?? 'operator') === 'superuser');

  // ---- conteggio dispositivi per stanza ----
  $devCount = [];
  foreach ($allDevices as $d) {
    /** @var IotDevice $d */
    $rid = (int)$d->getRoomId();
    $devCount[$rid] = (int)($devCount[$rid] ?? 0) + 1;
  }

  // ---- ordina stanze ----
  $sorted = $rooms;
  usort($sorted, function($a, $b) use ($orderBy, $devCount) {
    /** @var IotRoom $a */
    /** @var IotRoom $b */
    if ($orderBy === 'device_count') {
      $ca = (int)($devCount[(int)$a->getId()] ?? 0);
      $cb = (int)($devCount[(int)$b->getId()] ?? 0);
      return $cb <=> $ca; // desc
    }
    return strcasecmp((string)$a->getName(), (string)$b->getName());
  });
  $sorted = array_slice($sorted, 0, $limit);

  // ---- CSS locale del widget (scoped) ----
  $css = <<<CSS
    <style>
      .wrooms-cardgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
      .wroom-card{border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.08)}
      .wroom-img{aspect-ratio:16/9;background-size:cover;background-position:center}
      .wroom-body{padding:12px}
      .wroom-title{font-weight:800;font-size:18px;margin:2px 0 8px 0}
      .wbadges{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
      .wbadge{display:inline-block;padding:.28rem .6rem;border-radius:999px;font-size:12px;font-weight:800;border:1px solid transparent}
      .wblue{color:#1d4ed8;background:#eef2ff;border-color:#c7d2fe}
      .wgray{color:#374151;background:#f3f4f6;border-color:#e5e7eb}
      .wamber{color:#92400e;background:#fef3c7;border-color:#f59e0b}
      .wred{color:#b91c1c;background:#fee2e2;border-color:#fca5a5}
      .wactions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
      .wbtn{display:inline-flex;align-items:center;gap:6px;padding:.42rem .7rem;border-radius:10px;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none}
      .wbtn:hover{background:#f3f4f6}
    </style>
  CSS;

  // ---- griglia di card ----
  $cards = '<div class="wrooms-cardgrid">';

  if (!$sorted) {
    $cards .= '<div class="wroom-card"><div class="wroom-body">— Nessuna stanza —</div></div>';
  } else {
    foreach ($sorted as $r) {
      /** @var IotRoom $r */
      $rid   = (int)$r->getId();
      $name  = htmlspecialchars((string)$r->getName(), ENT_QUOTES, 'UTF-8');
      $floor = htmlspecialchars((string)($r->getFloorLabel() ?: '—'), ENT_QUOTES, 'UTF-8');
      $img   = (string)($r->getImage() ?? '');
      $img   = $img !== '' ? htmlspecialchars($img, ENT_QUOTES, 'UTF-8') : '';
      $bg    = $img !== '' ? "background-image:url('{$img}')" : "background-image:linear-gradient(135deg,#f0f4ff,#e5e7eb)";

      $count = (int)($devCount[$rid] ?? 0);
      $alarm = (string)($alarmMap[$rid] ?? 'disarmed');
      // badge allarme
      $alarmText = ($alarm === 'armed') ? 'Allarme: inserito' : (($alarm === 'triggered') ? 'Allarme: SCATTATO' : 'Allarme: disinserito');
      $alarmCls  = ($alarm === 'armed') ? 'wamber' : (($alarm === 'triggered') ? 'wred' : 'wblue');

      // actions
      $btnView = '<a class="wbtn" href="/Ardisafe2.0/room_view.php?id='.$rid.'">👁️ Show room</a>';
      $btnEdit = $isSuper ? '<a class="wbtn" href="/Ardisafe2.0/room_edit.php?id='.$rid.'">✏️ Modifica</a>' : '';
      $btnDel  = $isSuper ? '<a class="wbtn" href="/Ardisafe2.0/room_delete.php?id='.$rid.'">🗑️ Cancella</a>' : '';

      $cards .= '
        <div class="wroom-card">
          <div class="wroom-img" style="'.$bg.'"></div>
          <div class="wroom-body">
            <div class="wroom-title">'.$name.'</div>
            <div class="wbadges">
              <span class="wbadge wblue">'.$floor.'</span>
              <span class="wbadge wgray">Dispositivi: '.$count.'</span>
              <span class="wbadge '.$alarmCls.'">'.$alarmText.'</span>
            </div>
            <div class="wactions">'.$btnView.$btnEdit.$btnDel.'</div>
          </div>
        </div>';
    }
  }
  $cards .= '</div>';

  $title = htmlspecialchars($props['title'] ?? 'Stanze', ENT_QUOTES, 'UTF-8');
  return (new CLCard())->start()
           ->header($title)
           ->body($css.$cards, true)
           ->render();
}



function w_access_log(PDO $pdo, int $customerId, array $props): string {
  $limit = max(1, (int)($props['limit'] ?? 10));
  $rows = [];
  try {
    if (table_exists($pdo,'room_access_log')) {
      $st = $pdo->prepare("SELECT created_at, room_id, user_id FROM room_access_log WHERE customer_id=? ORDER BY created_at DESC LIMIT {$limit}");
      $st->execute([$customerId]); $rows = $st->fetchAll();
    } elseif (table_exists($pdo,'access_log')) {
      $st = $pdo->prepare("SELECT created_at, room_id, user_id FROM access_log WHERE customer_id=? ORDER BY created_at DESC LIMIT {$limit}");
      $st->execute([$customerId]); $rows = $st->fetchAll();
    }
  } catch (Throwable $e) {}

  $t = (new CLTable())->start()->theme('boxed');
  $t->header(['Data','Utente','Stanza']);
  if (!$rows) $t->row(['—','—','—']);
  foreach ($rows as $r) {
    $dt = htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? 'now')),ENT_QUOTES,'UTF-8');
    $who= 'User #'.(int)($r['user_id'] ?? 0);
    $rm = 'Room #'.(int)($r['room_id'] ?? 0);
    $t->row([$dt,$who,$rm]);
  }
  $title = htmlspecialchars($props['title'] ?? 'Accessi recenti',ENT_QUOTES,'UTF-8');
  return (new CLCard())->start()->header($title)->body($t->render(), true)->render();
}

function w_sensor_readings(PDO $pdo, int $customerId, array $props): string {
  $limit  = max(1, (int)($props['limit'] ?? 12));
  $metric = trim((string)($props['metric'] ?? ''));
  $rows = [];
  try {
    if (table_exists($pdo,'sensor_reading')) {
      if ($metric!=='') {
        $st = $pdo->prepare("SELECT device_id, metric, value, created_at FROM sensor_reading WHERE customer_id=? AND metric=? ORDER BY created_at DESC LIMIT {$limit}");
        $st->execute([$customerId,$metric]); $rows = $st->fetchAll();
      } else {
        $st = $pdo->prepare("SELECT device_id, metric, value, created_at FROM sensor_reading WHERE customer_id=? ORDER BY created_at DESC LIMIT {$limit}");
        $st->execute([$customerId]); $rows = $st->fetchAll();
      }
    }
  } catch (Throwable $e) {}

  $t = (new CLTable())->start()->theme('boxed');
  $t->header(['Data','Device','Metrica','Valore']);
  if (!$rows) $t->row(['—','—','—','—']);
  foreach ($rows as $r) {
    $dt = htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? 'now')),ENT_QUOTES,'UTF-8');
    $id = 'Dev #'.(int)($r['device_id'] ?? 0);
    $m  = htmlspecialchars((string)($r['metric'] ?? ''),ENT_QUOTES,'UTF-8');
    $v  = htmlspecialchars((string)($r['value']  ?? ''),ENT_QUOTES,'UTF-8');
    $t->row([$dt,$id,$m,$v]);
  }
  $title = htmlspecialchars($props['title'] ?? 'Letture sensori',ENT_QUOTES,'UTF-8');
  return (new CLCard())->start()->header($title)->body($t->render(), true)->render();
}

function w_kpi(array $rooms, array $allDevices, array $props): string {
  $metric = $props['metric'] ?? 'devices_total';
  $val = 0;
  if ($metric==='devices_total') {
    $val = count($allDevices);
  } elseif ($metric==='devices_online') {
    $val = 0; foreach ($allDevices as $d) if (strtolower($d->getStatus() ?? '')==='online') $val++;
  } elseif ($metric==='devices_offline') {
    $val = 0; foreach ($allDevices as $d) if (strtolower($d->getStatus() ?? '')==='offline') $val++;
  } elseif ($metric==='rooms_total') {
    $val = count($rooms);
  }
  $title = htmlspecialchars($props['title'] ?? 'KPI',ENT_QUOTES,'UTF-8');
  $body = '<div style="display:flex;align-items:center;justify-content:space-between">
    <div><div style="font-size:13px;color:#6b7280">'.$title.'</div><div style="font-weight:900;font-size:32px;line-height:1">'.$val.'</div></div>
    <div style="font-size:28px">📊</div>
  </div>';
  return (new CLCard())->start()->header($title)->body($body, true)->render();
}

/** Render sequenziale in griglia 2 colonne */
// DOPO (fluid: nessun max-width imposto dal grid)
$grid = (new CLGrid())->start()->container('fluid');
$grid->row(['g'=>20,'align'=>'start']);

foreach ($layout as $w) {
  $type  = $w['type']  ?? '';
  $props = is_array($w['props'] ?? null) ? $w['props'] : [];
  $card  = '';

  try {
    switch ($type) {
      case 'alarms_recent':
        $card = w_alarms_recent($pdo, $customerId, $props); break;
      case 'devices_offline':
        $card = w_devices_offline($allDevices, $props); break;
      case 'rooms_overview':
        $card = w_rooms_overview($rooms, $alarmMap, $allDevices, $props); break;
      case 'access_log':
        $card = w_access_log($pdo, $customerId, $props); break;
      case 'sensor_readings':
        $card = w_sensor_readings($pdo, $customerId, $props); break;
      case 'kpi':
        $card = w_kpi($rooms, $allDevices, $props); break;
      default:
        $card = (new CLCard())->start()->header('Widget non supportato')->body('<p>Tipo: '.htmlspecialchars($type,ENT_QUOTES,'UTF-8').'</p>', true)->render();
    }
  } catch (Throwable $e) {
    $card = (new CLCard())->start()->header('Errore widget')->body('<pre style="white-space:pre-wrap">'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</pre>', true)->render();
  }

  $grid->colRaw(12, ['lg'=>6], $card);
}

$grid->endRow();
echo $grid->render();

echo app()->close();
