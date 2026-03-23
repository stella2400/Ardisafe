<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** CSRF semplice */
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

/** Helper: current customer id */
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

$roomId = (int)($_GET['id'] ?? 0);
if ($roomId <= 0) { header('Location: /Ardisafe2.0/rooms.php'); exit; }

/** Repos opzionali */
$roomsRepo = new IotRooms();
if (!class_exists('IotRoomAlarms')) require_once __DIR__.'/plugins/iot/classes/IotRoomAlarms.php';

/** Carica stanza (repo → fallback SQL) */
$room = null;
try {
  if (method_exists($roomsRepo, 'getById')) {
    try { $room = $roomsRepo->getById($customerId, $roomId); }
    catch (ArgumentCountError $e) { $room = $roomsRepo->getById($roomId); }
  } elseif (method_exists($roomsRepo, 'find')) {
    try { $room = $roomsRepo->find($customerId, $roomId); }
    catch (ArgumentCountError $e) { $room = $roomsRepo->find($roomId); }
  }
} catch (Throwable $e) {}

if (!$room) {
  // Fallback diretto su DB
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
  $st = $pdo->prepare('SELECT * FROM room WHERE id = ? AND customer_id = ? LIMIT 1');
  $st->execute([$roomId, $customerId]);
  $row = $st->fetch();
  if ($row) {
    if (class_exists('IotRoom') && method_exists('IotRoom','fromRow')) {
      $room = IotRoom::fromRow($row);
    } elseif (class_exists('IotRoom')) {
      $room = new IotRoom();
      if (method_exists($room,'assign')) { $room->assign($row); }
      elseif (method_exists($room,'setId')) {
        $room->setId((int)$row['id']);
        if (method_exists($room,'setCustomerId')) $room->setCustomerId((int)$row['customer_id']);
        if (method_exists($room,'setName'))       $room->setName((string)$row['name']);
        if (method_exists($room,'setFloorLabel')) $room->setFloorLabel((string)($row['floor_label'] ?? ''));
        if (method_exists($room,'setImage'))      $room->setImage((string)($row['image'] ?? ''));
      }
    }
  }
}
if (!$room) {
  echo app()->open('Stanza');
  echo (new CLCard())->start()
        ->header('Stanza non trovata')
        ->body('<p>La stanza richiesta non esiste o non appartiene al tuo account.</p>'.
               (new CLButton())->link('↩︎ Torna alle stanze','/Ardisafe2.0/rooms.php',['variant'=>'secondary']), true);
  echo app()->close();
  exit;
}

/** Gestione toggle allarme (solo superuser) */
$alarmsRepo = new IotRoomAlarms();
if ($isSuper && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act'], $_POST['csrf']) && hash_equals($csrf, (string)$_POST['csrf'])) {
  if ($_POST['act'] === 'toggle') {
    $cur = $alarmsRepo->getState($roomId, $customerId);
    $next = $cur === 'armed' ? 'disarmed' : 'armed';
    if ($cur === 'triggered') $next = 'disarmed';
    $alarmsRepo->setState($roomId, $customerId, $next);
    header('Location: /Ardisafe2.0/room_view.php?id='.$roomId);
    exit;
  }
}
$state = $alarmsRepo->getState($roomId, $customerId);

/** PDO base */
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

/** ===== DISPOSITIVI: device + device_type.kind/model + device_position ===== */
$allDevices = [];
$unplaced   = [];
try {
  $sql = "SELECT
            d.id,
            COALESCE(NULLIF(d.name,''), CONCAT('Device #', d.id)) AS name,
            dt.kind AS kind,
            dt.model AS model,
            p.x_pct, p.y_pct,
            p.id AS pos_id
          FROM device d
          JOIN device_type dt ON dt.id = d.type_id
          LEFT JOIN device_position p
                 ON p.device_id = d.id
                AND p.room_id   = :rid
                AND p.customer_id = :cid
          WHERE d.room_id = :rid
            AND d.customer_id = :cid
          ORDER BY d.id ASC";
  $st = $pdo->prepare($sql);
  $st->execute([':rid'=>$roomId, ':cid'=>$customerId]);
  while ($r = $st->fetch()) {
    $row = [
      'id'   => (int)$r['id'],
      'name' => (string)$r['name'],
      'kind' => (string)$r['kind'],
      'model'=> (string)($r['model'] ?? ''),
      'x'    => is_null($r['x_pct']) ? null : (int)$r['x_pct'],
      'y'    => is_null($r['y_pct']) ? null : (int)$r['y_pct'],
      'pos_id' => $r['pos_id']
    ];
    $allDevices[] = $row;
    if ($row['pos_id'] === null) {
      $row['token'] = type_token($row['kind'], $row['model'] ?? '', $row['name']);
      $unplaced[] = $row;
    }
  }
} catch (Throwable $e) {
  $allDevices = [];
  $unplaced   = [];
}

/** Accessi / Allarmi (best-effort) */
$accessRows = [];
$alertRows  = [];
try {
  $st = $pdo->prepare('SELECT al.created_at, c.nome, c.cognome
                         FROM access_log al
                         JOIN customer c ON c.id = al.user_id
                        WHERE al.customer_id = ? AND al.room_id = ?
                        ORDER BY al.created_at DESC
                        LIMIT 10');
  $st->execute([$customerId, $roomId]);
  $accessRows = $st->fetchAll();
} catch (Throwable $e) {}

try {
  $st = $pdo->prepare('SELECT ae.captured_at, ae.image_url, ae.note
                         FROM alert_event ae
                        WHERE ae.customer_id = ? AND ae.room_id = ?
                        ORDER BY ae.captured_at DESC
                        LIMIT 10');
  $st->execute([$customerId, $roomId]);
  $alertRows = $st->fetchAll();
} catch (Throwable $e) {}

echo app()->open('Stanza');

/** ——— Mapping tipo → token → icona ——— */
function type_token(string $kind = '', ?string $model = null, string $name = ''): string {
  $hay = strtolower(trim(($model ?? '').' '.$name.' '.$kind));
  if (str_contains($hay,'pir') || str_contains($hay,'motion')) return 'pir';
  if (str_contains($hay,'door') || str_contains($hay,'magnet')) return 'door';
  if (str_contains($hay,'sir')) return 'siren';
  if (str_contains($hay,'thermo') || str_contains($hay,'temp')) return 'thermo';
  if (str_contains($hay,'hygro') || str_contains($hay,'humid')) return 'thermo';
  if (str_contains($hay,'cam')) return 'camera';
  if (str_contains($hay,'hub') || $kind === 'hub') return 'hub';
  if ($kind === 'sensor' || $kind === 'actuator') return $kind;
  return 'device';
}
function icon_by_token(string $token): string {
  return match ($token) {
    'thermo'  => '🌡️',
    'door'    => '🚪',
    'pir'     => '🏃🏻‍♂️',
    'camera'  => '🎥',
    'siren'   => '📢',
    'hub'     => '🧭',
    'actuator'=> '🛠️',
    'sensor'  => '🔵',
    default   => '🔵'
  };
}

/** pill di stato + classi */
$stateLabel = [
  'disarmed'  => 'DISINSERITO',
  'armed'     => 'INSERITO',
  'triggered' => 'SCATTATO',
][$state] ?? 'DISINSERITO';
$stateClass = match ($state) {
  'armed'     => 'alarm--armed',
  'triggered' => 'alarm--alert',
  default     => 'alarm--safe'
};

/** Form toggle (solo superuser) */
$toggleForm = '';
if ($isSuper) {
  $toggleForm = (new CLForm())
    ->start('/Ardisafe2.0/room_view.php?id='.$roomId, 'POST', ['class'=>'inline-form'])
    ->csrf('csrf', $csrf)
    ->hidden('act', 'toggle')
    ->submit($state === 'armed' ? 'Disattiva' : ($state === 'triggered' ? 'Reset' : 'Attiva'), ['variant'=>'secondary'])
    ->render();
}

/** Background mappa + titoli stanza */
$imgUrl  = $room->getImage() ? htmlspecialchars($room->getImage(), ENT_QUOTES, 'UTF-8') : '';
$bgStyle = $imgUrl !== '' ? "background-image:url('{$imgUrl}');" : "background-image:linear-gradient(135deg,#f0f4ff,#e5e7eb);";
$roomTitle = htmlspecialchars($room->getName() ?? 'Stanza', ENT_QUOTES, 'UTF-8');
$roomFloor = htmlspecialchars($room->getFloorLabel() ?? '', ENT_QUOTES, 'UTF-8');

/** Header custom con stato + toggle + Edit */
$headerHtml = '
  <div class="room-header">
    <div class="room-header__left">
      <div class="room-title">'.$roomTitle.'</div>'.
      ($roomFloor ? '<div class="room-sub">'.$roomFloor.'</div>' : '').
    '</div>
    <div class="room-header__right">
      <span class="alarm-pill '.$stateClass.'" title="Stato allarme">'.$stateLabel.'</span>'.
      ($isSuper ? '<span class="alarm-actions">'.$toggleForm.'</span>' : '').
      ($isSuper ? '<button type="button" class="btn-edit" id="btnEditMarkers">✏️ Edit</button>' : '').
    '</div>
  </div>
';

/** Markers HTML dai dati SQL */
$markers = '';
foreach ($allDevices as $r) {
  if ($r['x'] === null || $r['y'] === null) continue; // solo posizionati
  $id   = (int)$r['id'];
  $nm   = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
  $token= type_token($r['kind'], $r['model'] ?? '', $r['name']);
  $tp   = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
  $icon = icon_by_token($token);
  $x    = (int)$r['x'];
  $y    = (int)$r['y'];
  $href = '/Ardisafe2.0/device_view.php?id='.$id;

  $markers .= '<a class="marker" href="'.$href.'" data-id="'.$id.'" data-type="'.$tp.'" data-name="'.$nm.'" style="left:'.$x.'%; top:'.$y.'%" title="'.$nm.'">'.
                '<span class="marker-icon">'.$icon.'</span>'.
                '<span class="marker-label">'.$nm.'</span>'.
                ($isSuper ? '<button type="button" class="marker-del" title="Rimuovi">✖</button>' : '').
              '</a>';
}

/** Toolbar editor (solo superuser) */
$editorToolbar = '';
if ($isSuper) {
  $opts = '';
  foreach ($unplaced as $np) {
    $token = $np['token'] ?? type_token($np['kind'], $np['model'] ?? '', $np['name']);
    $opts .= '<option value="'.$np['id'].'" data-type="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'" data-name="'.htmlspecialchars($np['name'],ENT_QUOTES,'UTF-8').'">'
           . htmlspecialchars($np['name'].' — '.$token, ENT_QUOTES, 'UTF-8')
           . '</option>';
  }
  $editorToolbar = '
    <div class="editor-toolbar" id="editorToolbar" hidden>
      <div class="editor-tools">
        <label for="deviceToPlace">Aggiungi dispositivo:</label>
        <select id="deviceToPlace"><option value="">— Seleziona —</option>'.$opts.'</select>
        <button type="button" id="btnAddDevice">Aggiungi</button>
        <span class="hint">Trascina i marker per salvarne la posizione • Clic ✖ per rimuovere • Esc per uscire</span>
      </div>
    </div>
  ';
}

/** Card stanza con header custom + mappa */
$roomMapCard = (new CLCard())->start()
  ->header('') // header CLCard vuoto: usiamo l'header custom nel body
  ->body(
    $headerHtml.
    $editorToolbar.
    '<div class="room-map" id="roomMap" style="'.$bgStyle.'" data-room-id="'.$roomId.'" data-customer-id="'.$customerId.'" data-csrf="'.$csrf.'">'.
      $markers.
    '</div>',
    true
  );

/** Card accessi */
$tblAcc = (new CLTable())->start()->theme('boxed');
$tblAcc->header(['Utente','Data']);
if (!empty($accessRows)) {
  foreach ($accessRows as $r) {
    $who = htmlspecialchars(trim(($r['nome'] ?? '').' '.($r['cognome'] ?? '')), ENT_QUOTES, 'UTF-8');
    $dt  = htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8');
    $tblAcc->row([$who, $dt]);
  }
} else {
  $tblAcc->row(['—', '—']);
}
$accessCard = (new CLCard())->start()->header('Accessi recenti')->body($tblAcc->render(), true);

/** Card allarmi */
$tblAl = (new CLTable())->start()->theme('boxed');
$tblAl->header(['Data','Immagine','Nota']);
$tblAl->rawCols([1]);
if (!empty($alertRows)) {
  foreach ($alertRows as $r) {
    $dt  = htmlspecialchars(date('d/m/Y H:i', strtotime($r['captured_at'] ?? 'now')), ENT_QUOTES, 'UTF-8');
    $url = trim((string)($r['image_url'] ?? ''));
    $note= htmlspecialchars((string)($r['note'] ?? ''), ENT_QUOTES, 'UTF-8');
    $img = $url ? '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" title="Apri immagine">🖼️</a>' : '—';
    $tblAl->row([$dt, $img, $note]);
  }
} else {
  $tblAl->row(['—','—','—']);
}
$alertCard = (new CLCard())->start()->header('Allarmi recenti')->body($tblAl->render(), true);

/** Card dispositivi (tabella semplice) */
$tblDev = (new CLTable())->start()->theme('boxed');
$tblDev->header(['Dispositivo','Tipo','Valore','Aggiornato']);
foreach ($allDevices as $r) {
  $tblDev->row([
    htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'),
    htmlspecialchars(type_token($r['kind'], $r['model'] ?? '', $r['name']), ENT_QUOTES, 'UTF-8'),
    '—',
    '—'
  ]);
}
$devicesCard = (new CLCard())->start()->header('Dispositivi nella stanza')->body($tblDev->render(), true);

/** CSS locale */
echo <<<CSS
<style>
  .inline-form{display:inline-block;margin-left:8px}
  .inline-form .clform__actions{margin:0;display:inline}
  .inline-form button{margin:0}

  .clcard{margin-bottom:20px;} /* distacco tra card */

  .room-header{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:6px 4px 10px 4px;}
  .room-header__left{display:flex; align-items:baseline; gap:10px; flex-wrap:wrap}
  .room-title{font-weight:800; font-size:20px; line-height:1.1}
  .room-sub{font-size:14px; color:#6b7280}

  .alarm-pill{
    display:inline-block; padding:.45rem .75rem; border-radius:999px; font-weight:800; letter-spacing:.2px;
    border:1px solid transparent; font-size:13px;
  }
  .alarm--safe{ color:#1d4ed8; background:#eef2ff; border-color:#c7d2fe; }
  .alarm--armed{ color:#92400e; background:#fef3c7; border-color:#f59e0b; }
  .alarm--alert{ color:#b91c1c; background:#fee2e2; border-color:#fca5a5; }
  .alarm-actions{margin-left:8px}

  .btn-edit{ margin-left:10px; padding:.4rem .7rem; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; }
  .btn-edit[aria-pressed="true"]{background:#f3f4f6;}

  .room-map{
    position:relative; width:100%; aspect-ratio:16/9; border-radius:12px; background-size:cover; background-position:center;
    box-shadow:inset 0 0 0 1px rgba(0,0,0,.06); overflow:hidden;
  }
  .marker{
    position:absolute; transform:translate(-50%,-50%);
    background:rgba(17,24,39,.92); border-radius:999px;
    padding:.28rem .6rem; font-size:12px; white-space:nowrap;
    box-shadow:0 6px 14px rgba(0,0,0,.18);
    display:flex; align-items:center; gap:.4rem;
    user-select:none; text-decoration:none; max-width:260px;
    color:#fff !important;
  }
  .marker *{ color:#fff !important; }
  .marker-icon{font-size:14px; line-height:1}
  .marker-label{font-weight:700; overflow:hidden; text-overflow:ellipsis; display:inline-block; max-width:200px}

  .marker.drag-enabled{ cursor:move; }
  .marker-del{
    display:none; margin-left:.25rem; width:18px; height:18px; border:0; border-radius:9px;
    background:#ef4444; color:#fff; cursor:pointer; font-size:12px; line-height:18px; padding:0;
  }
  .editing .marker-del{ display:inline-flex; align-items:center; justify-content:center; }

  .editor-toolbar{ margin:8px 0 10px 0; padding:8px; border:1px dashed #d1d5db; border-radius:10px; background:#f9fafb; }
  .editor-tools{display:flex; align-items:center; gap:8px; flex-wrap:wrap}
  .editor-tools select{padding:.35rem .5rem}
  .editor-tools button{padding:.4rem .7rem; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer;}
  .editor-tools .hint{color:#6b7280; font-size:12px; margin-left:4px}
</style>
CSS;

/** Layout */
echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], $roomMapCard->render())
  ->endRow()
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, ['lg'=>6], $accessCard->render())
    ->colRaw(12, ['lg'=>6], $alertCard->render())
  ->endRow()
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, [], $devicesCard->render())
  ->endRow()
  ->render();
?>
<script>
(function(){
  const isSuper = <?php echo $isSuper ? 'true' : 'false'; ?>;
  if(!isSuper) return;

  const map = document.getElementById('roomMap');
  const btnEdit = document.getElementById('btnEditMarkers');
  const toolbar = document.getElementById('editorToolbar');
  const sel = document.getElementById('deviceToPlace');
  const btnAdd = document.getElementById('btnAddDevice');

  let editMode = false;
  let dragging = null;
  let ghost   = null;

  function pct(x, total){ return Math.max(0, Math.min(100, (x/total)*100)); }

  // token → emoji
  function iconFor(token){
    const t = (token || '').toLowerCase();
    if (t.includes('thermo') || t.includes('temp')) return '🌡️';
    if (t.includes('door')) return '🚪';
    if (t.includes('pir') || t.includes('motion')) return '🏃🏻‍♂️';
    if (t.includes('camera') || t.includes('cam')) return '🎥';
    if (t.includes('siren')) return '📢';
    if (t.includes('hub')) return '🧭';
    if (t.includes('actuator')) return '🛠️';
    return '🔵';
  }

  function enableDrag(elem){
    elem.classList.add('drag-enabled');
    elem.addEventListener('pointerdown', onDragStart);
  }
  function disableDrag(elem){
    elem.classList.remove('drag-enabled');
    elem.removeEventListener('pointerdown', onDragStart);
  }

  function onDragStart(ev){
    if(!editMode) return;
    if(ev.target.closest('.marker-del')) return;
    dragging = ev.currentTarget;
    try { dragging.setPointerCapture(ev.pointerId); } catch(_) {}
    ev.preventDefault();
  }
  function onPointerMove(ev){
    if(!editMode) return;
    const rect = map.getBoundingClientRect();
    const x = ev.clientX - rect.left;
    const y = ev.clientY - rect.top;
    const xp = pct(x, rect.width);
    const yp = pct(y, rect.height);
    if(ghost){ ghost.style.left = xp + '%'; ghost.style.top  = yp + '%'; }
    if(dragging){ dragging.style.left = xp + '%'; dragging.style.top  = yp + '%'; }
  }
  function onPointerUp(ev){
    if(dragging){
      saveMarker(dragging);
      try { dragging.releasePointerCapture(ev.pointerId); } catch(_) {}
      dragging = null;
    }
  }

  function saveMarker(elem){
    const deviceId = parseInt(elem.dataset.id,10);
    const left = parseFloat(elem.style.left);
    const top  = parseFloat(elem.style.top);

    fetch('/Ardisafe2.0/room_position_save.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        csrf: map.dataset.csrf,
        room_id: parseInt(map.dataset.roomId,10),
        customer_id: parseInt(map.dataset.customerId,10),
        device_id: deviceId,
        x_pct: Math.round(left * 1000)/1000,
        y_pct: Math.round(top  * 1000)/1000
      })
    }).then(r=>r.json()).then(j=>{
      if(!j || j.ok !== true){
        alert('Errore salvataggio posizione' + (j && j.detail ? (': ' + j.detail) : ''));
      }
    }).catch(()=> alert('Errore rete salvataggio'));
  }

  function deleteMarker(elem){
    const deviceId = parseInt(elem.dataset.id,10);
    fetch('/Ardisafe2.0/room_position_delete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        csrf: map.dataset.csrf,
        room_id: parseInt(map.dataset.roomId,10),
        customer_id: parseInt(map.dataset.customerId,10),
        device_id: deviceId
      })
    }).then(r=>r.json()).then(j=>{
      if(j && j.ok === true){
        elem.remove();
        if(sel){
          const opt = document.createElement('option');
          opt.value = String(deviceId);
          opt.setAttribute('data-type', elem.dataset.type || '');
          opt.setAttribute('data-name', elem.dataset.name || ('Device #'+deviceId));
          opt.textContent = (elem.dataset.name || ('Device #'+deviceId)) + ' — ' + (elem.dataset.type || 'device');
          sel.appendChild(opt);
        }
      } else {
        alert('Errore rimozione' + (j && j.detail ? (': ' + j.detail) : ''));
      }
    }).catch(()=> alert('Errore rete rimozione'));
  }

  function createMarker(deviceId, label, token){
    const a = document.createElement('a');
    a.className = 'marker drag-enabled';
    a.dataset.id = deviceId;
    a.dataset.type = token || '';
    a.dataset.name = label || ('Device #'+deviceId);
    a.href = '/Ardisafe2.0/device_view.php?id=' + deviceId;
    a.style.left = '50%';
    a.style.top  = '50%';
    a.innerHTML = '<span class="marker-icon">'+iconFor(token)+'</span><span class="marker-label"></span>' +
                  '<button type="button" class="marker-del" title="Rimuovi">✖</button>';
    a.querySelector('.marker-label').textContent = label || ('Device #'+deviceId);

    a.querySelector('.marker-del').addEventListener('click', (e)=>{
      e.preventDefault(); e.stopPropagation();
      if(confirm('Rimuovere il marker?')) deleteMarker(a);
    });

    enableDrag(a);
    return a;
  }

  function enterEdit(){
    editMode = true;
    btnEdit.setAttribute('aria-pressed','true');
    toolbar.hidden = false;
    map.classList.add('editing');

    map.querySelectorAll('.marker').forEach(m=>{
      const del = m.querySelector('.marker-del');
      if(del){
        del.onclick = (e)=>{ e.preventDefault(); e.stopPropagation(); if(confirm('Rimuovere il marker?')) deleteMarker(m); };
      }
      enableDrag(m);
    });
    map.addEventListener('pointermove', onPointerMove);
    map.addEventListener('pointerup', onPointerUp);
    window.addEventListener('keydown', onKey);
  }
  function exitEdit(){
    editMode = false;
    btnEdit.removeAttribute('aria-pressed');
    toolbar.hidden = true;
    map.classList.remove('editing');
    map.querySelectorAll('.marker').forEach(disableDrag);
    map.removeEventListener('pointermove', onPointerMove);
    map.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('keydown', onKey);
    if(ghost){ ghost.remove(); ghost = null; }
  }
  function onKey(e){ if(e.key === 'Escape') exitEdit(); }

  // In edit blocco click sui marker ma LASCIO passare i click su ✖
  map.addEventListener('click', (e)=>{
    if(!editMode) return;
    if(e.target.closest('.marker-del')) return;
    const a = e.target.closest('.marker');
    if(a){ e.preventDefault(); e.stopPropagation(); }
  }, true);

  btnEdit && btnEdit.addEventListener('click', ()=>{
    if(editMode) exitEdit(); else enterEdit();
  });

  // Aggiungi nuovo dispositivo non posizionato
  btnAdd && btnAdd.addEventListener('click', ()=>{
    const option = sel && sel.options[sel.selectedIndex];
    const id = parseInt(option?.value || '0', 10);
    if(!id){ alert('Seleziona un dispositivo'); return; }

    const label = option.getAttribute('data-name') || (option.text || '').replace(/ — .+$/,'').trim();
    const token = option.getAttribute('data-type') || '';

    ghost = createMarker(id, label, token);
    map.appendChild(ghost);

    const rect = map.getBoundingClientRect();
    ghost.style.left = '50%'; ghost.style.top = '50%';

    const onFirstClick = (ev)=>{
      if(!ghost) return;
      const x = (ev.clientX - rect.left);
      const y = (ev.clientY - rect.top);
      const xp = Math.max(0, Math.min(100, (x/rect.width)*100));
      const yp = Math.max(0, Math.min(100, (y/rect.height)*100));
      ghost.style.left = xp+'%'; ghost.style.top = yp+'%';
      saveMarker(ghost);
      map.removeEventListener('click', onFirstClick, true);
      option.remove(); // ora è posizionato
      ghost = null;
    };
    map.addEventListener('click', onFirstClick, true);
  });

})();
</script>
<?php
echo app()->close();
