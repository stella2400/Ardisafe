<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** helper: current customer id */
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

$q        = trim((string)($_GET['q'] ?? ''));
$roomId   = (int)($_GET['room_id'] ?? 0);
$typeId   = (int)($_GET['type_id'] ?? 0);
$status   = trim((string)($_GET['status'] ?? ''));

// repos
$roomRepo = new IotRooms();
$rooms    = $roomRepo->listByCustomer($customerId, '');
$typeRepo = class_exists('IotDeviceTypes') ? new IotDeviceTypes() : null;
$types    = $typeRepo ? $typeRepo->listAll() : []; // opzionale
$devRepo  = new IotDevices();

// carico dispositivi e filtro
$all = $devRepo->listAll($customerId, $q, 2000, 0, 'last_seen DESC');
$filtered = array_values(array_filter($all, function(IotDevice $d) use ($roomId,$typeId,$status){
  if ($roomId>0  && (int)$d->getRoomId() !== $roomId) return false;
  if ($typeId>0  && (int)$d->getTypeId() !== $typeId) return false;
  if ($status!=='' && $d->getStatus() !== $status)    return false;
  return true;
}));

// mappe id->label
$roomMap = [];
foreach ($rooms as $r) { $roomMap[$r->getId()] = $r->getName(); }

$typeMap = [];
if ($types) {
  foreach ($types as $t) {
    $label = trim(($t->getKind() ?? '').' / '.($t->getVendor() ?? '').($t->getModel() ? (' '.$t->getModel()) : ''));
    $typeMap[$t->getId()] = $label !== '' ? $label : ('#'.$t->getId());
  }
}

// UI
echo app()->open('Dispositivi');

// filtri
$selectRooms = [''=>'Tutte le stanze'];
foreach ($rooms as $r) $selectRooms[(string)$r->getId()] = $r->getName();

$selectTypes = [''=>'Tutti i tipi'];
foreach ($types as $t) $selectTypes[(string)$t->getId()] = $typeMap[$t->getId()];

$selectStatus= [''=>'Tutti','online'=>'Online','offline'=>'Offline','unknown'=>'Sconosciuto'];

$filters = (new CLForm())
  ->start('/Ardisafe2.0/devices.php','GET',['id'=>'filter-devices'])
  ->text('q','Cerca', ['placeholder'=>'Nome o Ident','value'=>$q])
  ->select('room_id','Stanza', $selectRooms, ['value'=>$roomId? (string)$roomId :''])
  ->select('type_id','Tipo',   $selectTypes, ['value'=>$typeId? (string)$typeId :''])
  ->select('status', 'Stato',  $selectStatus, ['value'=>$status])
  ->submit('Filtra', ['variant'=>'secondary'])
  ->render();

$btnNew = $isSuper
  ? (new CLButton())->link('➕ Nuovo dispositivo','/Ardisafe2.0/device_edit.php',['variant'=>'primary'])
  : '';

$cardTop = (new CLCard())->start()
  ->header('Dispositivi', 'Gestione inventario IoT')
  ->body(
    '<div class="toolbar" style="display:flex;gap:12px;align-items:flex-end;justify-content:space-between">'
    .'<div style="flex:1">'.$filters.'</div>'
    .'<div>'.$btnNew.'</div>'
    .'</div>', true
  );

// tabella elenco
$tab = (new CLTable())->start()->theme('boxed');
$tab->header(['Nome','Ident','Tipo','Stanza','Stato','Ultimo visto','Azioni']);
$tab->rawCols([4,6]); // << indici corretti: 0..6 (Stato, Azioni)

$badgeSt = function(string $s): string {
  $s = strtolower($s);
  $map = [
    'online'  => 'background:#e8fff0;color:#036d2f;border:1px solid #a5f3c6',
    'offline' => 'background:#fff1f2;color:#9f1239;border:1px solid #fecdd3',
    'unknown' => 'background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe',
  ];
  $style = $map[$s] ?? $map['unknown'];
  return '<span class="badge" style="display:inline-block;padding:.2rem .5rem;border-radius:999px;font-weight:700;font-size:12px;'.$style.'">'.htmlspecialchars($s,ENT_QUOTES).'</span>';
};

foreach ($filtered as $d) {
  /** @var IotDevice $d */
  $did   = (int)$d->getId();
  $name  = htmlspecialchars($d->getName(),ENT_QUOTES);
  $ident = htmlspecialchars($d->getIdent(),ENT_QUOTES);
  $type  = htmlspecialchars($typeMap[$d->getTypeId()] ?? ('#'.$d->getTypeId()),ENT_QUOTES);
  $room  = htmlspecialchars($roomMap[$d->getRoomId() ?? 0] ?? '—',ENT_QUOTES);
  $st    = $badgeSt($d->getStatus());
  $seen  = $d->getLastSeen() ? date('d/m/Y H:i', strtotime($d->getLastSeen())) : '—';

  $actions = (new CLButton())->startGroup(['merge'=>true])
    ->link('🗂️','/Ardisafe2.0/device_view.php?id='.$did,['variant'=>'secondary','attrs'=>['title'=>'Scheda']])
    ->link('✏️','/Ardisafe2.0/device_edit.php?id='.$did,['variant'=>'secondary','attrs'=>['title'=>'Modifica']])
    ->render();

  $tab->row([$name,$ident,$type,$room,$st,$seen,$actions]);
}

$cardList = (new CLCard())->start()
  ->header('Elenco dispositivi')
  ->body($tab->render(), true);

// spacing extra tra le due card
echo '<style>.grid-row-gap{margin-top:16px}</style>';

echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], $cardTop->render())
  ->endRow()
  ->row(['g'=>20,'align'=>'start','class'=>'grid-row-gap','style'=>'margin-top:16px'])
    ->colRaw(12, [], $cardList->render())
  ->endRow()
  ->render();

echo app()->close();
