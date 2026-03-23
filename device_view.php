<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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

  $st = $pdo->prepare('SELECT id FROM customer WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $row = $st->fetch();
  if ($row && !empty($row['id'])) {
    $_SESSION['user']['id'] = (int)$row['id'];
    return (int)$row['id'];
  }
  return 0;
}

$customerId = current_customer_id();
$deviceId   = (int)($_GET['id'] ?? 0);
if ($customerId <= 0 || $deviceId <= 0) {
  echo app()->open('Dispositivo');
  echo (new CLCard())->start()->header('Dispositivo non trovato')->body(
    (new CLButton())->link('↩︎ Torna','/Ardisafe2.0/rooms.php',['variant'=>'secondary']), true
  );
  echo app()->close(); exit;
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

/** === Fetch device base + type + room === */
$st = $pdo->prepare("
  SELECT d.*, dt.kind AS type_kind, dt.model AS type_model, dt.vendor AS type_vendor,
         r.name AS room_name, r.id AS room_id
  FROM device d
  LEFT JOIN device_type dt ON dt.id = d.type_id
  LEFT JOIN room r ON r.id = d.room_id
  WHERE d.id = ? AND d.customer_id = ?
  LIMIT 1
");
$st->execute([$deviceId, $customerId]);
$dev = $st->fetch();

if (!$dev) {
  echo app()->open('Dispositivo');
  echo (new CLCard())->start()->header('Dispositivo non trovato')->body(
    (new CLButton())->link('↩︎ Torna','/Ardisafe2.0/rooms.php',['variant'=>'secondary']), true
  );
  echo app()->close(); exit;
}

/** Helpers: token & icona per tipo */
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

/** Stato & colori */
$rawStatus   = strtolower(trim((string)($dev['status'] ?? 'unknown')));
$isThreshold = false;
try {
  $q = $pdo->prepare("SELECT 1 FROM event WHERE device_id = ? AND created_at >= (NOW() - INTERVAL 2 HOUR) LIMIT 1");
  $q->execute([$deviceId]);
  $isThreshold = (bool)$q->fetch();
} catch (Throwable $e) {
  $meta = $dev['meta'] ?? null;
  if (is_string($meta)) { $meta = json_decode($meta, true); }
  if (is_array($meta) && !empty($meta['threshold'])) $isThreshold = true;
}
$heroState = $isThreshold ? 'threshold' : (in_array($rawStatus, ['online','offline'], true) ? $rawStatus : 'unknown');

/** Dati UI */
$name       = htmlspecialchars($dev['name'] ?: ('Device #'.$dev['id']), ENT_QUOTES, 'UTF-8');
$roomName   = htmlspecialchars($dev['room_name'] ?: ('Stanza #'.$dev['room_id']), ENT_QUOTES, 'UTF-8');
$typeToken  = type_token((string)($dev['type_kind'] ?? ''), (string)($dev['type_model'] ?? ''), (string)$dev['name']);
$icon       = icon_by_token($typeToken);
$vendor     = htmlspecialchars((string)($dev['type_vendor'] ?? ''), ENT_QUOTES, 'UTF-8');
$model      = htmlspecialchars((string)($dev['type_model'] ?? ''), ENT_QUOTES, 'UTF-8');
$statusLbl  = $heroState === 'online' ? 'Online' : ($heroState === 'offline' ? 'Offline' : ($heroState === 'threshold' ? 'Threshold' : 'Sconosciuto'));
$lastSeen   = !empty($dev['updated_at']) ? date('d/m/Y H:i', strtotime($dev['updated_at'])) : '—';
$rssi       = '—';
$battery    = '—';
$meta       = $dev['meta'] ?? null;
if (is_string($meta)) { $meta = json_decode($meta, true); }
if (is_array($meta)) {
  if (isset($meta['rssi']))    $rssi = (string)$meta['rssi'];
  if (isset($meta['battery'])) $battery = (string)$meta['battery'];
}

/** Tab: Info (due card interne con titoli) */
$infoGrid = '
  <div class="info-grid">
    <div class="subcol">
      '.(new CLCard())->start()->header('Informazioni')->body(
        '<div class="info-list">
           <div><strong>Tipo:</strong> '.htmlspecialchars($typeToken,ENT_QUOTES,'UTF-8').'</div>
           <div><strong>Vendor:</strong> '.$vendor.'</div>
           <div><strong>Modello:</strong> '.$model.'</div>
           <div><strong>Identificativo:</strong> '.htmlspecialchars((string)$dev['ident'],ENT_QUOTES,'UTF-8').'</div>
         </div>', true
      )->render().'
    </div>
    <div class="subcol">
      '.(new CLCard())->start()->header('Grafico')->body(
        '<div id="chartContainer" class="chart-placeholder">Grafico in arrivo…</div>', true
      )->render().'
    </div>
  </div>
';

/** Tab: Thresholds (card con titolo) */
$thresholdsHtml =
  (new CLCard())->start()->header('Thresholds')->body(
    '<div class="placeholder"><p>Configurazioni threshold in arrivo…</p></div>', true
  )->render();

/** Tab: Storico (card con titolo) */
$storicoHtml =
  (new CLCard())->start()->header('Storico')->body(
    '<div class="placeholder"><p>Storico misure in arrivo…</p></div>', true
  )->render();

echo app()->open('Dispositivo');
?>
<style>
  /* Nasconde SOLO gli header CLCard vuoti (non tocca le card interne con titolo) */
  .clcard > .clcard__header:empty { display:none; }

  .hero{ border-radius:12px; padding:18px 20px; color:#fff; margin-bottom:14px; }
  .hero--online{ background: linear-gradient(90deg, #10b981, #0ea5e9); }
  .hero--offline{ background: linear-gradient(90deg, #ef4444, #b91c1c); }
  .hero--threshold{ background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
  .hero--unknown{ background: linear-gradient(90deg, #6b7280, #374151); }

  .hero__top{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .hero__left{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .hero__icon{ font-size:28px; line-height:1; background: rgba(255,255,255,.18); border-radius:12px; padding:8px 10px; }
  .hero__title{ font-weight:800; font-size:20px; }
  .hero__subtitle{ opacity:.9; font-size:13px; }
  .hero__stats{ display:flex; gap:12px; flex-wrap:wrap; font-size:13px; }
  .hero__stat{ display:flex; align-items:center; gap:6px; background: rgba(255,255,255,.12); padding:6px 10px; border-radius:999px; }
  .status-dot{ width:8px; height:8px; border-radius:50%; background:#fff; opacity:.9; }
  .btn-back{ background: rgba(255,255,255,.15); color:#fff; padding:8px 12px; border-radius:8px; text-decoration:none; border:1px solid rgba(255,255,255,.25); }

  /* Tabs */
  .tabs{ display:flex; gap:8px; border-bottom:1px solid #e5e7eb; margin:8px 0 16px 0; }
  .tab{
    appearance:none; background:#fff; border:1px solid #e5e7eb; border-bottom:0;
    padding:8px 12px; border-top-left-radius:8px; border-top-right-radius:8px; cursor:pointer; font-size:14px;
  }
  .tab.is-active{ background:#f9fafb; font-weight:700; }
  .tab-panel{ display:none; }
  .tab-panel.is-active{ display:block; }

  /* layout dentro tab Info */
  .info-grid{ display:grid; grid-template-columns:1fr; gap:16px; }
  @media (min-width: 992px){ .info-grid{ grid-template-columns: 1fr 1fr; } }
  .info-list{ display:grid; gap:8px; font-size:14px; }
  .chart-placeholder{
    height:300px; display:flex; align-items:center; justify-content:center;
    color:#6b7280; background:#f9fafb; border-radius:12px; border:1px dashed #e5e7eb;
  }
  .placeholder{ padding:10px; color:#6b7280; }
</style>
<?php

/* Card esterna di contorno (header vuoto nascosto via :empty) */
$cardBody = '
  <div class="hero hero--'.$heroState.'">
    <div class="hero__top">
      <div class="hero__left">
        <div class="hero__icon">'.$icon.'</div>
        <div>
          <div class="hero__title">'.$name.'</div>
          <div class="hero__subtitle">'.$roomName.'</div>
        </div>
      </div>
      <div class="hero__stats">
        <div class="hero__stat"><span class="status-dot"></span><span>'.$statusLbl.'</span></div>
        <div class="hero__stat">Battery: <strong>'.htmlspecialchars($battery,ENT_QUOTES,'UTF-8').'</strong></div>
        <div class="hero__stat">RSSI: <strong>'.htmlspecialchars($rssi,ENT_QUOTES,'UTF-8').'</strong></div>
        <div class="hero__stat">Last Update: <strong>'.htmlspecialchars($lastSeen,ENT_QUOTES,'UTF-8').'</strong></div>
        <a class="btn-back" href="/Ardisafe2.0/room_view.php?id='.(int)$dev['room_id'].'">↩︎ Torna alla stanza</a>
      </div>
    </div>
  </div>

  <div class="tabs" role="tablist" aria-label="Schede dispositivo">
    <button class="tab is-active" role="tab" aria-selected="true" aria-controls="tab-info"  id="t-info">Info</button>
    <button class="tab"         role="tab" aria-selected="false" aria-controls="tab-thr"   id="t-thr">Thresholds</button>
    <button class="tab"         role="tab" aria-selected="false" aria-controls="tab-hist"  id="t-hist">Storico</button>
  </div>

  <section id="tab-info" class="tab-panel is-active" role="tabpanel" aria-labelledby="t-info">
    '.$infoGrid.'
  </section>
  <section id="tab-thr" class="tab-panel" role="tabpanel" aria-labelledby="t-thr">
    '.$thresholdsHtml.'
  </section>
  <section id="tab-hist" class="tab-panel" role="tabpanel" aria-labelledby="t-hist">
    '.$storicoHtml.'
  </section>
';

echo (new CLCard())->start()->header('')->body('<div class="device-card">'.$cardBody.'</div>', true)->render();

echo app()->close();
?>

<script>
// Tab switcher
(function(){
  const tabs = Array.from(document.querySelectorAll('.tab'));
  const panels = {
    info:  document.getElementById('tab-info'),
    thr:   document.getElementById('tab-thr'),
    hist:  document.getElementById('tab-hist')
  };
  function activate(which){
    tabs.forEach(t => {
      const active = (t.id === 't-'+which);
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true':'false');
    });
    Object.entries(panels).forEach(([k,el])=>{
      el.classList.toggle('is-active', k === which);
    });
  }
  document.getElementById('t-info')?.addEventListener('click', ()=>activate('info'));
  document.getElementById('t-thr') ?.addEventListener('click', ()=>activate('thr'));
  document.getElementById('t-hist')?.addEventListener('click', ()=>activate('hist'));
})();
</script>
