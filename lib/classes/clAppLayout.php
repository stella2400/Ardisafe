<?php
declare(strict_types=1);

/**
 * CLAppLayout — v1.4
 * - Topbar condivisa con hamburger (drawer)
 * - Menù con filtri per ruolo (superuser vs operator)
 * - Idle timer client-side: logout dopo N minuti di inattività
 * - CSS/JS inclusi una sola volta
 *
 * Dipendenze: CLButton
 */
class CLAppLayout
{
    protected static bool $stylePrinted  = false;
    protected static bool $scriptPrinted = false;

    protected string $brandHtml = '<strong>ArdiSafe</strong>';
    protected string $logoutUrl = '/Ardisafe2.0/logout.php';
    protected string $wrapClass = 'pagewrap';
    protected string $bodyClass = '';
    protected array  $headExtras = [];

    /** minuti di inattività (client-side) prima del logout */
    protected int $idleMinutes = 15;

    /**
     * Menù normalizzato:
     *   ['label'=>string,'href'=>string,'roles'=>string[]|null]
     */
    protected array $menuItems = [
        ['label'=>'Dashboard',    'href'=>'/Ardisafe2.0/homepage.php', 'roles'=>null],
        ['label'=>'Profilo',      'href'=>'/Ardisafe2.0/profile.php',  'roles'=>null],
        ['label'=>'Impostazioni', 'href'=>'/Ardisafe2.0/settings.php', 'roles'=>null],
        ['label'=>'Report',       'href'=>'/Ardisafe2.0/reports.php',  'roles'=>null],
        // ['label'=>'Utenti', 'href'=>'/Ardisafe2.0/users.php', 'roles'=>['superuser']],
    ];

    public function brand(string $html): self      { $this->brandHtml = $html; return $this; }
    public function logoutUrl(string $url): self   { $this->logoutUrl = $url; return $this; }
    public function bodyClass(string $cls): self   { $this->bodyClass = $cls; return $this; }
    public function headExtra(string $html): self  { $this->headExtras[] = $html; return $this; }
    public function idleMinutes(int $m): self      { $this->idleMinutes = max(1, $m); return $this; }

    /** Imposta il menù (accetta forme semplici o complete con roles) */
    public function menu(array $items): self
    {
        $norm = $this->normalizeMenu($items);
        if ($norm) $this->menuItems = $norm;
        return $this;
    }

    /** Apre pagina (topbar + drawer + contenitore) */
    public function open(string $title = 'ArdiSafe'): string
    {
        $out = [];
        $out[] = '<!doctype html><html lang="it"><head>';
        $out[] = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $out[] = '<title>'.$this->esc($title).'</title>';
        if (!self::$stylePrinted)  { $out[] = $this->styleTag();  self::$stylePrinted  = true; }
        if (!self::$scriptPrinted) { $out[] = $this->scriptTag(); self::$scriptPrinted = true; }
        foreach ($this->headExtras as $h) { $out[] = $h; }
        $out[] = '</head><body data-idle-minutes="'.$this->idleMinutes.'"'.($this->bodyClass ? ' class="'.$this->escAttr($this->bodyClass).'"' : '').'>';

        // Topbar
        $logoutBtn = (new CLButton())->link('Logout', $this->logoutUrl, ['variant'=>'secondary']);
        $out[] = '<div class="topbar">';
        $out[] = '  <div class="left">';
        $out[] = '    <button class="hamburger js-appdrawer-toggle" aria-label="Apri menù" aria-controls="appdrawer" aria-expanded="false"><span class="bars"></span></button>';
        $out[] = '    <div class="brand">'.$this->brandHtml.'</div>';
        $out[] = '  </div>';
        #$out[] = '  <div class="actions">'.$logoutBtn.'</div>';
        $out[] = '</div>';

        // Drawer + backdrop
        $out[] = '<nav id="appdrawer" class="appdrawer" aria-hidden="true">';
        $out[] = '  <div class="drawer-head">Menu</div>';
        $out[] = '  <div class="drawer-body">';
        $role = $this->currentRole();
        foreach ($this->menuItems as $it) {
            if (!$this->isVisibleForRole($it['roles'], $role)) continue;
            $out[] = '    <a class="drawer-link" href="'.$this->escAttr($it['href']).'">'.$this->esc($it['label']).'</a>';
        }
        $out[] = '  </div>';
        $out[] = '</nav>';
        $out[] = '<div class="drawer-backdrop js-appdrawer-close" hidden></div>';

        // Wrapper contenuti
        $out[] = '<div class="'.$this->escAttr($this->wrapClass).'">';
        return implode("\n", $out);
    }

    public function close(): string
    {
        return "</div>\n</body></html>";
    }

    /* ============ internals ============ */
    protected function currentRole(): string
    {
        return (isset($_SESSION['user']['ruolo']) && is_string($_SESSION['user']['ruolo']))
            ? $_SESSION['user']['ruolo']
            : 'operator';
    }

    protected function isVisibleForRole(?array $roles, string $current): bool
    {
        if ($roles === null) return true;
        if ($roles === [])   return false;
        return in_array($current, $roles, true);
    }

    protected function normalizeMenu(array $items): array
    {
        $out = [];
        foreach ($items as $k => $v) {
            if (is_array($v) && isset($v['label'], $v['href'])) {
                $out[] = [
                    'label' => (string)$v['label'],
                    'href'  => (string)$v['href'],
                    'roles' => isset($v['roles']) && is_array($v['roles']) ? array_values(array_map('strval', $v['roles'])) : null,
                ];
            } elseif (is_string($v)) {
                $label = is_string($k) ? $k : $v;
                $out[] = ['label'=>$label, 'href'=>$v, 'roles'=>null];
            }
        }
        return $out;
    }

    protected function styleTag(): string
    {
        return <<<CSS
<style>
:root{
  --app-bg:#f6f7fb;
  --app-text:#111827;
  --app-topbar-bg:#111827;
  --app-topbar-fg:#fff;
  --app-wrap-max:1280px;
  --drawer-bg:#0f172a;
  --drawer-fg:#e5e7eb;
  --drawer-border:rgba(255,255,255,.08);
}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--app-bg);color:var(--app-text)}
.topbar{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 12px 0 8px;background:var(--app-topbar-bg);color:var(--app-topbar-fg);position:sticky;top:0;z-index:50}
.topbar .left{display:flex;align-items:center;gap:8px}
.topbar .brand{font-weight:700}
.topbar .actions{display:flex;align-items:center;gap:8px}
/* Hamburger */
.hamburger{width:40px;height:34px;border-radius:10px;border:1px solid transparent;background:transparent;color:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.hamburger:hover{background:rgba(255,255,255,.08)}
.hamburger .bars{position:relative;display:block;width:18px;height:2px;background:currentColor;border-radius:2px}
.hamburger .bars::before,.hamburger .bars::after{content:"";position:absolute;left:0;width:18px;height:2px;background:currentColor;border-radius:2px}
.hamburger .bars::before{top:-6px}
.hamburger .bars::after{top:6px}
/* Layout wrapper */
.pagewrap{padding:24px;max-width:var(--app-wrap-max);margin:0 auto}
@media (max-width: 480px){ .pagewrap{padding:16px} }
/* Drawer */
.appdrawer{position:fixed;left:0;top:56px;bottom:0;width:260px;background:var(--drawer-bg);color:var(--drawer-fg);border-right:1px solid var(--drawer-border);transform:translateX(-102%);transition:transform .22s ease;z-index:60}
body.appdrawer-open .appdrawer{transform:none}
.drawer-head{padding:14px 16px;border-bottom:1px solid var(--drawer-border);font-weight:700}
.drawer-body{padding:8px 0}
.drawer-link{display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--drawer-fg);text-decoration:none}
.drawer-link:hover{background:rgba(255,255,255,.06)}
/* Backdrop */
.drawer-backdrop{position:fixed;left:0;right:0;top:56px;bottom:0;background:rgba(15,23,42,.4);opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:55}
body.appdrawer-open .drawer-backdrop{opacity:1;pointer-events:auto}
</style>
CSS;
    }

    protected function scriptTag(): string
    {
        $logoutUrl   = $this->escAttr($this->logoutUrl);
        return <<<JS
<script>
(function(){
  if (window.__app_init) return; window.__app_init = true;

  // Drawer
  function openDrawer(){ document.body.classList.add('appdrawer-open'); var btn=document.querySelector('.js-appdrawer-toggle'); if(btn) btn.setAttribute('aria-expanded','true'); var dr=document.getElementById('appdrawer'); if(dr) dr.setAttribute('aria-hidden','false'); document.querySelector('.drawer-backdrop')?.removeAttribute('hidden'); }
  function closeDrawer(){ document.body.classList.remove('appdrawer-open'); var btn=document.querySelector('.js-appdrawer-toggle'); if(btn) btn.setAttribute('aria-expanded','false'); var dr=document.getElementById('appdrawer'); if(dr) dr.setAttribute('aria-hidden','true'); document.querySelector('.drawer-backdrop')?.setAttribute('hidden',''); }
  document.addEventListener('click', function(e){
    var t = e.target.closest('.js-appdrawer-toggle'); if(t){ e.preventDefault(); if(document.body.classList.contains('appdrawer-open')) closeDrawer(); else openDrawer(); return; }
    if (e.target.closest('.js-appdrawer-close')) { e.preventDefault(); closeDrawer(); }
    if (e.target.closest('.drawer-link')) { closeDrawer(); }
  });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDrawer(); });

  // Idle logout (client-side)
  var idleMinAttr = parseInt(document.body.getAttribute('data-idle-minutes')||'15',10);
  var IDLE_MS = Math.max(1, idleMinAttr) * 60 * 1000; // default 15
  var timer = null;
  function onIdle(){ window.location.href = '{$logoutUrl}?timeout=1'; }
  function resetTimer(){ if (timer) clearTimeout(timer); timer = setTimeout(onIdle, IDLE_MS); }
  ['mousemove','mousedown','keydown','scroll','touchstart','focus','visibilitychange'].forEach(function(evt){
    document.addEventListener(evt, function(){ if(evt==='visibilitychange' && document.hidden) return; resetTimer(); }, {passive:true});
  });
  resetTimer();
})();
</script>
JS;
    }

    protected function esc(string $v): string    { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    protected function escAttr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
