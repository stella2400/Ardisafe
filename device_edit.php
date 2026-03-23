<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';
if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// ---- helper per ottenere il current customer id in modo robusto ----
function current_customer_id(): int {
  if (!empty($_SESSION['user']['id'])) {
    return (int)$_SESSION['user']['id'];
  }
  // fallback via email
  $email = $_SESSION['user']['email'] ?? null;
  if (!$email) return 0;

  // prendi un PDO affidabile (usa $pdo da config.php se presente)
  $pdo = $GLOBALS['pdo'] ?? null;
  if (!$pdo instanceof PDO) {
    // fallback minimo (adatta se necessario)
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
    $_SESSION['user']['id'] = (int)$row['id'];        // sincronizza la sessione
    if (!empty($row['ruolo'])) $_SESSION['user']['ruolo'] = $row['ruolo']; // opzionale
    return (int)$row['id'];
  }
  return 0;
}

$viewer   = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper  = ($viewer['ruolo'] ?? 'operator') === 'superuser';
$customerId = current_customer_id();
if (!$isSuper) { header('Location: /Ardisafe2.0/devices.php'); exit; }

$id = (int)($_GET['id'] ?? 0);

$roomRepo = new IotRooms();
$rooms    = $roomRepo->listByCustomer($customerId,'');
$typeRepo = new IotDeviceTypes();
$types    = $typeRepo->listAll();
$devRepo  = new IotDevices();

$device = $id>0 ? $devRepo->findById($id) : new IotDevice(['customer_id'=>$customerId, 'status'=>'unknown']);
if ($id>0 && (!$device || $device->getCustomerId() !== $customerId)) { header('Location: /Ardisafe2.0/devices.php'); exit; }

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf']) || !hash_equals($csrf,(string)$_POST['csrf'])) {
    $errors[]='Sessione scaduta. Riprova.';
  } else {
    $name = trim((string)($_POST['name'] ?? ''));
    $ident= trim((string)($_POST['ident'] ?? ''));
    $typeId = (int)($_POST['type_id'] ?? 0);
    $roomId = (int)($_POST['room_id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'unknown');
    $meta   = trim((string)($_POST['meta'] ?? ''));

    if ($name==='')  $errors[]='Nome obbligatorio.';
    if ($ident==='') $errors[]='Identificativo obbligatorio.';
    if ($typeId<=0)  $errors[]='Seleziona il tipo.';
    if (!in_array($status,['online','offline','unknown'],true)) $errors[]='Stato non valido.';
    $metaArr = null;
    if ($meta!=='') {
      $metaArr = json_decode($meta,true);
      if (!is_array($metaArr)) $errors[]='Meta deve essere JSON valido.';
    }

    if (!$errors) {
      $device->assign([
        'customer_id'=>$customerId,
        'name'=>$name,
        'ident'=>$ident,
        'type_id'=>$typeId,
        'room_id'=>$roomId>0 ? $roomId : null,
        'status'=>$status,
        'meta'=>$metaArr
      ]);
      if ($id>0) $devRepo->update($device); else $id=$devRepo->create($device);
      header('Location: /Ardisafe2.0/devices.php'); exit;
    }
  }
}

echo app()->open($id>0?'Modifica dispositivo':'Nuovo dispositivo');

$msg=''; foreach ($errors as $e) $msg.='<div class="alert alert-danger" style="padding:10px;border-radius:8px;margin-bottom:10px;">'.htmlspecialchars($e).'</div>';

$selRooms = [''=>'(nessuna)']; foreach ($rooms as $r) $selRooms[(string)$r->getId()] = $r->getName();
$selTypes = []; foreach ($types as $t) { $label = $t->getKind().' / '.($t->getVendor()??'').($t->getModel()?(' '.$t->getModel()):''); $selTypes[(string)$t->getId()]=$label; }
$selStatus= ['unknown'=>'Sconosciuto','online'=>'Online','offline'=>'Offline'];

$form = (new CLForm())
  ->start('/Ardisafe2.0/device_edit.php'.($id?('?id='.$id):''),'POST',['id'=>'device-form'])
  ->csrf('csrf',$csrf)
  ->text('name','Nome dispositivo',['required'=>true,'value'=>htmlspecialchars($device?->getName() ?? '',ENT_QUOTES)])
  ->text('ident','Identificativo (unico)',['required'=>true,'placeholder'=>'Es. MAC/UUID/SN','value'=>htmlspecialchars($device?->getIdent() ?? '',ENT_QUOTES)])
  ->select('type_id','Tipo dispositivo', $selTypes, ['required'=>true,'value'=>$device? (string)$device->getTypeId() : ''])
  ->select('room_id','Stanza', $selRooms, ['value'=>$device? (string)($device->getRoomId() ?? '') : ''])
  ->select('status','Stato',$selStatus,['value'=>$device? $device->getStatus() : 'unknown'])
  ->text('meta','Meta (JSON opzionale)',['placeholder'=>'{"key":"value"}','value'=>htmlspecialchars($device && $device->getMeta()? json_encode($device->getMeta(),JSON_UNESCAPED_UNICODE) : '',ENT_QUOTES)])
  ->submit($id>0?'Salva':'Crea',['variant'=>'primary']);

$card = (new CLCard())->start()
  ->header($id>0?'Modifica dispositivo':'Nuovo dispositivo')
  ->body($msg.$form->render(), true);

echo (new CLGrid())->start()->container('xl')->row(['g'=>20])->colRaw(12,[],$card->render())->endRow()->render();

echo app()->close();
