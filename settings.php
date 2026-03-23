<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** CSRF */
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } catch(Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

/** Helper: current customer id */
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

/** Crea tabella per layout homepage (se non esiste) */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS user_homepage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    widgets_json LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_home (customer_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/** Shortcut */
$customerId = current_customer_id();
if ($customerId <= 0) { header('Location: /Ardisafe2.0/login.php'); exit; }
$user       = $_SESSION['user'] ?? [];
$userId     = (int)($user['id'] ?? $customerId);
$isSuper    = (($user['ruolo'] ?? 'operator') === 'superuser');

/** Catalogo widget disponibile */
$WIDGET_CATALOG = [
  // key => [title, description, default props]
  'alarms_recent'   => ['Allarmi recenti',     'Ultimi eventi di allarme/snapshot.',          ['title'=>'Allarmi recenti','limit'=>10,'hours'=>24]],
  'devices_offline' => ['Dispositivi offline', 'Device offline o non visti da N minuti.',      ['title'=>'Dispositivi offline','limit'=>10,'offline_threshold_min'=>15]],
  'rooms_overview'  => ['Stanze',              'Elenco stanze con stato allarme e conteggi.',  ['title'=>'Stanze','limit'=>12,'order_by'=>'name','show_alarm'=>true]],
  'access_log'      => ['Accessi recenti',     'Ultimi accessi registrati.',                   ['title'=>'Accessi recenti','limit'=>10]],
  'sensor_readings' => ['Letture sensori',     'Ultime letture metrica (es. temp).',           ['title'=>'Letture sensori','limit'=>12,'metric'=>'']],
  'kpi'             => ['KPI',                 'Riquadro KPI (conteggi rapidi).',              ['title'=>'KPI','metric'=>'devices_total']], // devices_total|devices_online|devices_offline|rooms_total
];

/** Carica layout corrente (o default) */
$st = $pdo->prepare("SELECT widgets_json FROM user_homepage WHERE customer_id=? AND user_id=? LIMIT 1");
$st->execute([$customerId, $userId]);
$row = $st->fetch();

if ($row && !empty($row['widgets_json'])) {
  $layout = json_decode($row['widgets_json'], true);
  if (!is_array($layout)) $layout = [];
} else {
  // default iniziale
  $layout = [
    ['id'=>uniqid('w_'), 'type'=>'alarms_recent',   'props'=>$WIDGET_CATALOG['alarms_recent'][2]],
    ['id'=>uniqid('w_'), 'type'=>'devices_offline', 'props'=>$WIDGET_CATALOG['devices_offline'][2]],
    ['id'=>uniqid('w_'), 'type'=>'rooms_overview',  'props'=>$WIDGET_CATALOG['rooms_overview'][2]],
  ];
}

/** Salvataggio layout */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['csrf']) && hash_equals($csrf, (string)$_POST['csrf'])) {
  if (isset($_POST['act']) && $_POST['act']==='save_home' && isset($_POST['layout_json'])) {
    $json = (string)$_POST['layout_json'];
    $arr  = json_decode($json, true);
    // normalizza: array di widget con {id,type,props}
    $normalized = [];
    if (is_array($arr)) {
      foreach ($arr as $w) {
        if (!is_array($w)) continue;
        $type = $w['type'] ?? '';
        if (!isset($WIDGET_CATALOG[$type])) continue;
        $id   = (string)($w['id'] ?? uniqid('w_'));
        $props= is_array($w['props'] ?? null) ? $w['props'] : [];
        // merge con default props del catalogo
        $props = array_merge($WIDGET_CATALOG[$type][2], $props);
        // sanitizzazione base
        $props['title'] = substr(trim((string)($props['title'] ?? $WIDGET_CATALOG[$type][2]['title'])), 0, 80);
        foreach (['limit','hours','offline_threshold_min'] as $k) {
          if (isset($props[$k])) $props[$k] = max(1, (int)$props[$k]);
        }
        if (isset($props['order_by']) && !in_array($props['order_by'], ['name','device_count'], true)) $props['order_by'] = 'name';
        if (isset($props['show_alarm'])) $props['show_alarm'] = (bool)$props['show_alarm'];
        if (isset($props['metric']) && $type==='kpi') {
          if (!in_array($props['metric'], ['devices_total','devices_online','devices_offline','rooms_total'], true)) {
            $props['metric'] = 'devices_total';
          }
        }
        $normalized[] = ['id'=>$id,'type'=>$type,'props'=>$props];
      }
    }
    if (!$normalized) { // evita layout vuoto che “spacca” la home
      $normalized = [
        ['id'=>uniqid('w_'), 'type'=>'alarms_recent', 'props'=>$WIDGET_CATALOG['alarms_recent'][2]],
      ];
    }

    $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare("INSERT INTO user_homepage (customer_id,user_id,widgets_json) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE widgets_json=VALUES(widgets_json), updated_at=CURRENT_TIMESTAMP");
    $st->execute([$customerId, $userId, $payload]);
    header('Location: /Ardisafe2.0/settings.php?saved=home'); exit;
  }
}

/** === UI === */
echo app()->open('Impostazioni');

/** Stili minimi */
echo '<style>
  .clcard{margin-bottom:20px}
  .home-builder{display:grid; grid-template-columns: 1fr 2fr; gap:16px;}
  @media (max-width: 1024px){ .home-builder{grid-template-columns: 1fr; } }
  .catalog{display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:10px;}
  .wcard{border:1px solid #e5e7eb; border-radius:12px; padding:10px;}
  .wcard h4{margin:0 0 6px 0; font-size:14px}
  .wcard p{margin:0 0 8px 0; color:#6b7280; font-size:12px; min-height:32px}
  .btn{display:inline-block; padding:.35rem .6rem; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:13px;}
  .btn:disabled{opacity:.5; cursor:not-allowed}
  .picked-list{display:flex; flex-direction:column; gap:8px; min-height:120px;}
  .picked-item{border:1px solid #e5e7eb; border-radius:10px; padding:8px; background:#fff;}
  .pi-head{display:flex; align-items:center; justify-content:space-between; gap:8px}
  .pi-title{font-weight:700}
  .pi-actions button{margin-left:6px}
  .pi-body{display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-top:8px}
  .pi-body label{font-size:12px; color:#374151}
  .pi-body input[type="text"], .pi-body input[type="number"], .pi-body select {width:100%; padding:.35rem .4rem; border:1px solid #d1d5db; border-radius:8px}
  .pi-body .full{grid-column: 1 / -1}
</style>';

/** Palette (sinistra) */
$catHtml = '<div class="catalog" id="catalog">';
foreach ($WIDGET_CATALOG as $key=>$def) {
  $catHtml .= '<div class="wcard" data-type="'.htmlspecialchars($key,ENT_QUOTES,'UTF-8').'">
    <h4>'.htmlspecialchars($def[0],ENT_QUOTES,'UTF-8').'</h4>
    <p>'.htmlspecialchars($def[1],ENT_QUOTES,'UTF-8').'</p>
    <button type="button" class="btn add-widget" data-type="'.htmlspecialchars($key,ENT_QUOTES,'UTF-8').'">Aggiungi</button>
  </div>';
}
$catHtml .= '</div>';

$left = (new CLCard())->start()
  ->header('Homepage – Widget disponibili', 'Scegli cosa vuoi vedere nella tua homepage')
  ->body($catHtml, true);

/** Lista widget scelti (destra) + form di salvataggio */
$builderHtml = '
  <form id="homeForm" method="post" action="/Ardisafe2.0/settings.php">
    <input type="hidden" name="csrf" value="'.htmlspecialchars($csrf,ENT_QUOTES,'UTF-8').'">
    <input type="hidden" name="act" value="save_home">
    <input type="hidden" name="layout_json" id="layout_json" value="">
    <div class="picked-list" id="pickedList"></div>
    <div style="margin-top:10px">
      <button type="submit" class="btn" id="btnSave">💾 Salva homepage</button>
      <a class="btn" href="/Ardisafe2.0/homepage.php?dashboard=1" target="_blank">👁️ Anteprima</a>
    </div>
  </form>
';

$right = (new CLCard())->start()
  ->header('La tua homepage', 'Ordina, configura e salva')
  ->body('<div class="home-builder"><div></div><div>'.$builderHtml.'</div></div>', true);

/** Layout pagina impostazioni */
echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20,'align'=>'start'])
    ->colRaw(12, ['lg'=>5], $left->render())
    ->colRaw(12, ['lg'=>7], $right->render())
  ->endRow()
  ->render();

/** JS Builder */
$catalogJS = json_encode(array_map(function($def){ return $def[2]; }, $WIDGET_CATALOG), JSON_UNESCAPED_UNICODE);
$layoutJS  = json_encode($layout, JSON_UNESCAPED_UNICODE);
?>
<script>
(function(){
  const CATALOG_DEFAULT_PROPS = <?php echo $catalogJS ?>; // {type: defaultProps}
  let layout = <?php echo $layoutJS ?>;                    // [{id,type,props}, ...]

  const pickedList = document.getElementById('pickedList');
  const catalog    = document.getElementById('catalog');
  const payload    = document.getElementById('layout_json');
  const form       = document.getElementById('homeForm');

  function uid(){ return 'w_' + Math.random().toString(36).slice(2,10); }

  function render(){
    pickedList.innerHTML = '';
    layout.forEach((w, idx)=>{
      const el = document.createElement('div');
      el.className = 'picked-item';
      el.dataset.id = w.id;

      const title = (w.props && w.props.title) ? w.props.title : (w.type);
      el.innerHTML = `
        <div class="pi-head">
          <div class="pi-title">${escapeHtml(title)} <small style="color:#6b7280">(${escapeHtml(w.type)})</small></div>
          <div class="pi-actions">
            <button type="button" class="btn btn-up" ${idx===0 ? 'disabled' : ''}>↑</button>
            <button type="button" class="btn btn-down" ${idx===(layout.length-1) ? 'disabled' : ''}>↓</button>
            <button type="button" class="btn btn-del">✖</button>
          </div>
        </div>
        <div class="pi-body">
          <div class="full"><label>Titolo<input type="text" class="pi-title-input" value="${escapeAttr(w.props.title || '')}"></label></div>
          ${configFields(w).join('')}
        </div>
      `;
      // listeners
      el.querySelector('.btn-up').addEventListener('click', ()=>{ move(idx, -1); });
      el.querySelector('.btn-down').addEventListener('click', ()=>{ move(idx, +1); });
      el.querySelector('.btn-del').addEventListener('click', ()=>{ remove(idx); });
      el.querySelector('.pi-title-input').addEventListener('input', (e)=>{ w.props.title = e.target.value; sync(); });

      // campi specifici
      wireSpecificInputs(el, w);

      pickedList.appendChild(el);
    });
    sync();
  }

  function configFields(w){
    // restituisce array di snippet HTML con i campi per il widget
    const fields = [];
    if (w.type==='alarms_recent'){
      fields.push(`<div><label>Limite<input type="number" class="pi-input" data-k="limit" value="${num(w.props.limit,10)}"></label></div>`);
      fields.push(`<div><label>Ultime ore<input type="number" class="pi-input" data-k="hours" value="${num(w.props.hours,24)}"></label></div>`);
    } else if (w.type==='devices_offline'){
      fields.push(`<div><label>Limite<input type="number" class="pi-input" data-k="limit" value="${num(w.props.limit,10)}"></label></div>`);
      fields.push(`<div><label>Offline se > min<input type="number" class="pi-input" data-k="offline_threshold_min" value="${num(w.props.offline_threshold_min,15)}"></label></div>`);
    } else if (w.type==='rooms_overview'){
      fields.push(`<div><label>Limite<input type="number" class="pi-input" data-k="limit" value="${num(w.props.limit,12)}"></label></div>`);
      fields.push(`<div><label>Ordina per
        <select class="pi-input" data-k="order_by">
          <option value="name" ${sel(w.props.order_by,'name')}>Nome</option>
          <option value="device_count" ${sel(w.props.order_by,'device_count')}># Dispositivi</option>
        </select></label></div>`);
      fields.push(`<div class="full"><label><input type="checkbox" class="pi-input" data-k="show_alarm" ${w.props.show_alarm ? 'checked':''}> Mostra stato allarme</label></div>`);
    } else if (w.type==='access_log'){
      fields.push(`<div><label>Limite<input type="number" class="pi-input" data-k="limit" value="${num(w.props.limit,10)}"></label></div>`);
    } else if (w.type==='sensor_readings'){
      fields.push(`<div><label>Limite<input type="number" class="pi-input" data-k="limit" value="${num(w.props.limit,12)}"></label></div>`);
      fields.push(`<div><label>Metrica (opz.)<input type="text" class="pi-input" data-k="metric" value="${escapeAttr(w.props.metric || '')}"></label></div>`);
    } else if (w.type==='kpi'){
      fields.push(`<div class="full"><label>Metrica
        <select class="pi-input" data-k="metric">
          <option value="devices_total" ${sel(w.props.metric,'devices_total')}># Dispositivi Totali</option>
          <option value="devices_online" ${sel(w.props.metric,'devices_online')}># Dispositivi Online</option>
          <option value="devices_offline" ${sel(w.props.metric,'devices_offline')}># Dispositivi Offline</option>
          <option value="rooms_total" ${sel(w.props.metric,'rooms_total')}># Stanze Totali</option>
        </select></label></div>`);
    }
    return fields;
  }

  function wireSpecificInputs(container, w){
    container.querySelectorAll('.pi-input').forEach(inp=>{
      inp.addEventListener('input', (e)=>{
        const k = e.target.dataset.k;
        if (!k) return;
        if (e.target.type === 'checkbox') {
          w.props[k] = !!e.target.checked;
        } else if (e.target.type === 'number') {
          w.props[k] = Math.max(1, parseInt(e.target.value||'0',10));
        } else {
          w.props[k] = e.target.value;
        }
        sync();
      });
    });
  }

  function move(idx, delta){
    const to = idx + delta;
    if (to < 0 || to >= layout.length) return;
    const [w] = layout.splice(idx,1);
    layout.splice(to,0,w);
    render();
  }
  function remove(idx){
    layout.splice(idx,1);
    render();
  }

  function sync(){
    payload.value = JSON.stringify(layout);
  }

  // palette "Aggiungi"
  catalog.querySelectorAll('.add-widget').forEach(b=>{
    b.addEventListener('click', ()=>{
      const type = b.dataset.type;
      const props = Object.assign({}, CATALOG_DEFAULT_PROPS[type] || {});
      layout.push({ id: uid(), type, props });
      render();
    });
  });

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
  function escapeAttr(s){ return escapeHtml(s); }
  function num(v,d){ v=parseInt(v||d,10); return isNaN(v)?d:v; }
  function sel(v,a){ return (v===a) ? 'selected' : ''; }

  // prima render
  render();

  // doppio guard rail: se l’utente invia senza widget -> blocca
  form.addEventListener('submit', (e)=>{
    if (!layout || !layout.length) {
      e.preventDefault();
      alert('Aggiungi almeno un widget alla homepage.');
    }
  });
})();
</script>
<?php
echo app()->close();
