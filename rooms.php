<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** CSRF per azioni (es. delete) */
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

/** Helper: ID customer corrente */
function current_customer_id(): int {
  if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];

  $email = $_SESSION['user']['email'] ?? null;
  if (!$email) return 0;

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

  $st = $pdo->prepare('SELECT id, ruolo FROM customer WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $row = $st->fetch();
  if ($row && !empty($row['id'])) {
    $_SESSION['user']['id'] = (int)$row['id'];
    if (!empty($row['ruolo'])) $_SESSION['user']['ruolo'] = $row['ruolo'];
    return (int)$row['id'];
  }
  return 0;
}

$viewer      = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper     = ($viewer['ruolo'] ?? 'operator') === 'superuser';
$customerId  = current_customer_id();
if ($customerId <= 0) { header('Location: /Ardisafe2.0/login.php'); exit; }

$q = trim((string)($_GET['q'] ?? ''));

// Repos
$roomsRepo = new IotRooms();
$rooms     = $roomsRepo->listByCustomer($customerId, $q, 500, 0, 'name ASC');

// Conteggio dispositivi
$devCount = [];
try {
  $devRepo  = new IotDevices();
  $devices  = $devRepo->listAll($customerId, '', 10000, 0, 'id DESC');
  foreach ($devices as $d) { $rid = $d->getRoomId() ?: 0; $devCount[$rid] = ($devCount[$rid] ?? 0) + 1; }
} catch (Throwable $e) {}

// Stati allarme per le stanze mostrate
$alarmStates = [];
if (!empty($rooms)) {
  $roomIds = array_map(fn($r) => (int)$r->getId(), $rooms);
  if (!class_exists('IotRoomAlarms')) { require_once __DIR__.'/plugins/iot/classes/IotRoomAlarms.php'; }
  if (!class_exists('IotRoomAlarm'))  { require_once __DIR__.'/plugins/iot/classes/IotRoomAlarm.php'; }
  $alarmsRepo = new IotRoomAlarms();
  $alarmStates = $alarmsRepo->getStatesByRoomIds($roomIds, $customerId);
}

// UI
echo app()->open('Stanze');

// Toolbar (filtro + nuovo)
$filters = (new CLForm())
  ->start('/Ardisafe2.0/rooms.php','GET',['id'=>'filter-rooms'])
  ->text('q','', [
    'placeholder'=>'Cerca per nome o piano…',
    'value'=>$q,
    'attrs'=>['style'=>'min-width:220px']
  ])
  ->submit('Filtra', ['variant'=>'secondary'])
  ->render();

$btnNew = $isSuper
  ? (new CLButton())->link('➕ Nuova stanza','/Ardisafe2.0/room_edit.php', ['variant'=>'primary'])
  : '';

$cardTop = (new CLCard())->start()
  ->header('Stanze', 'Gestione aree e piani')
  ->body(
    '<div class="toolbar" style="display:flex;gap:12px;align-items:flex-end;justify-content:space-between">'.
      '<div style="flex:1">'.$filters.'</div>'.
      '<div>'.$btnNew.'</div>'.
    '</div>'.
    '<style>
      .toolbar .clform{display:flex;gap:8px;align-items:flex-end;background:transparent;border:none;padding:0}
      .toolbar .clform__group{margin:0}
      .toolbar .clform__actions{margin:0}
    </style>'
  , true);

// CSS card + badge
echo <<<CSS
<style>
  .room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
  .room-card{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .room-card__img{height:220px;background-size:cover;background-position:center}
  .room-card__body{padding:12px}
  .room-card__title{font-weight:800;font-size:16px;margin:0 0 8px}

  .room-card__meta{display:flex;gap:10px;align-items:center;margin-bottom:6px}
  .room-badge{
    display:inline-block;padding:.35rem .75rem;border-radius:999px;
    font-weight:700;font-size:12px;line-height:1;border:1px solid transparent;
  }
  /* Piano (primaria blu) */
  .room-badge--floor{color:#1d4ed8;background:#eef2ff;border-color:#c7d2fe;}
  /* Dispositivi (neutra) */
  .room-badge--devices{color:#334155;background:#f1f5f9;border-color:#e2e8f0;}
  /* Allarme: disinserito (blu), inserito (ambra), scattato (rosso) */
  .room-badge--alarm-safe{color:#1d4ed8;background:#eef2ff;border-color:#c7d2fe;}
  .room-badge--alarm-armed{color:#92400e;background:#fef3c7;border-color:#f59e0b;}
  .room-badge--alarm-alert{color:#b91c1c;background:#fee2e2;border-color:#fca5a5;}

  .room-card__actions{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  /* Inline form per bottone Cancella dentro le azioni */
  .room-card__actions form.clform{display:inline-block;background:transparent;border:none;padding:0;margin:0}
  .room-card__actions form.clform .clform__actions{display:inline}
  .room-card__actions form.clform .clform__actions button{margin:0}

  @media (max-width: 420px){ .room-grid{grid-template-columns:1fr} }
</style>
CSS;

// Griglia
$cardsHtml = '';

if (empty($rooms)) {
  $cardsHtml = '<div style="padding:24px;text-align:center;color:#6b7280">Nessuna stanza trovata.'.
               ($isSuper ? ' <br>Inizia creando la tua prima stanza.' : '').
               '</div>';
} else {
  foreach ($rooms as $r) {
    /** @var IotRoom $r */
    $rid   = (int)$r->getId();
    $name  = htmlspecialchars($r->getName(), ENT_QUOTES, 'UTF-8');
    $floor = htmlspecialchars($r->getFloorLabel() ?? '—', ENT_QUOTES, 'UTF-8');
    $cnt   = (int)($devCount[$rid] ?? 0);

    // immagine
    $imgUrl  = $r->getImage() ? htmlspecialchars($r->getImage(), ENT_QUOTES, 'UTF-8') : '';
    $bgStyle = $imgUrl !== '' ? "background-image:url('{$imgUrl}');"
                              : "background-image:linear-gradient(135deg,#eef2ff,#e0e7ff);";

    // stato allarme da mappa
    $state = $alarmStates[$rid] ?? 'disarmed';
    switch ($state) {
      case 'armed':     $alarmClass = 'room-badge--alarm-armed'; $alarmLabel = 'Allarme: inserito'; break;
      case 'triggered': $alarmClass = 'room-badge--alarm-alert'; $alarmLabel = 'Allarme: SCATTATO'; break;
      default:          $alarmClass = 'room-badge--alarm-safe';  $alarmLabel = 'Allarme: disinserito';
    }

    // azioni: Show, Modifica (super), Cancella (super con POST+CSRF)
    $btns = new CLButton();
    $btns->startGroup(['merge'=>true,'class'=>'room-actions'])
         ->link('🚪 Show room', "/Ardisafe2.0/room_view.php?id={$rid}", [
            'variant'=>'secondary','attrs'=>['title'=>'Entra nella stanza']
         ]);
    if ($isSuper) {
      $btns->link('✏️ Modifica', "/Ardisafe2.0/room_edit.php?id={$rid}", [
        'variant'=>'secondary','attrs'=>['title'=>'Modifica stanza']
      ]);
      $btns->link('🗑️ Cancella', "/Ardisafe2.0/room_delete.php?id={$rid}", [
        'variant'=>'secondary','attrs'=>['title'=>'Modifica stanza']
      ]);

    }
    $actions = $btns->render();


    $cardsHtml .=
      '<div class="room-card">'.
        '<div class="room-card__img" style="'.$bgStyle.'"></div>'.
        '<div class="room-card__body">'.
          '<div class="room-card__title">'.$name.'</div>'.
          '<div class="room-card__meta">'.
            '<span class="room-badge room-badge--floor">'.$floor.'</span>'.
            '<span class="room-badge room-badge--devices">Dispositivi: '.$cnt.'</span>'.
            '<span class="room-badge '.$alarmClass.'">'.$alarmLabel.'</span>'.
          '</div>'.
          '<div class="room-card__actions">'.$actions.'</div>'.
        '</div>'.
      '</div>';
  }
}

$cardsWrapper = '<div class="room-grid">'.$cardsHtml.'</div>';

$cardList = (new CLCard())->start()
  ->header('Elenco stanze')
  ->body($cardsWrapper, true);

// Layout con distacco certo tra le card
echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], '<div style="margin-bottom:28px">'.$cardTop->render().'</div>')
  ->endRow()
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], $cardList->render())
  ->endRow()
  ->render();

echo app()->close();
