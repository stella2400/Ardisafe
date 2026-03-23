<?php
/**
 * CLModal.php — v1.0
 *
 * Modal/Dialog & Drawer (senza dipendenze esterne)
 * - CSS integrato una sola volta (disattivabile con ->noDefaultCss())
 * - JS leggero incluso una sola volta per open/close, ESC, overlay-click, focus-trap, body-lock
 * - Varianti: modal centrato (default) · drawer: right | left | bottom | top
 * - Taglie: sm | md | lg | xl
 * - Sezioni: header(title/actions), body, footer; pulsante di chiusura integrato
 * - Accessibilità: role="dialog", aria-modal, aria-labelledby/aria-describedby
 * - API fluente: start()->variant()->size()->title()->body()->footer()->render()
 * - Helper: triggerButton(label, attrs) per aprire la modale senza HTML manuale
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLModal
{
    // ====== Config ======
    protected static bool $stylePrinted = false;
    protected static bool $scriptPrinted = false;

    protected bool $includeDefaultCss = true;
    protected bool $includeDefaultJs = true;

    protected string $variant = 'modal'; // modal | drawer
    protected string $drawerSide = 'right'; // right|left|bottom|top (se variant=drawer)

    protected string $size = 'md'; // sm|md|lg|xl
    protected bool $closable = true; // overlay-click & ESC

    protected ?string $id = null;
    /** @var array<string,mixed> */
    protected array $attrs = ['class' => 'clmodal'];

    protected ?string $title = null;
    protected ?string $idTitle = null;
    protected ?string $desc = null;
    protected ?string $idDesc = null;

    protected bool $openOnLoad = false;

    // content blocks
    protected ?string $headerActionsRaw = null;
    protected string $bodyContent = '';
    protected bool $bodyRaw = false;
    protected ?string $footerRaw = null;

    // ====== API ======
    public function start(?string $id = null, array $attrs = []): self
    {
        $this->id = $id ?: $this->genId();
        $this->idTitle = $this->id.'-title';
        $this->idDesc  = $this->id.'-desc';
        $this->attrs = array_replace($this->attrs, ['id' => $this->id], $attrs);
        $this->ensureClass('clmodal');
        return $this;
    }

    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function noDefaultJs(): self  { $this->includeDefaultJs = false;  return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }
    public function useDefaultJs(): self  { $this->includeDefaultJs = true;  return $this; }

    public function variant(string $v, string $side = 'right'): self
    {   // v: modal|drawer
        $v = in_array($v, ['modal','drawer'], true) ? $v : 'modal';
        $this->variant = $v;
        if ($v === 'drawer') {
            $this->drawerSide = in_array($side, ['right','left','bottom','top'], true) ? $side : 'right';
        }
        return $this;
    }

    public function drawer(string $side = 'right'): self { return $this->variant('drawer', $side); }

    public function size(string $s): self
    {
        $allowed = ['sm','md','lg','xl'];
        if (in_array($s, $allowed, true)) $this->size = $s;
        return $this;
    }

    public function closable(bool $b): self { $this->closable = $b; return $this; }
    public function openOnLoad(bool $b = true): self { $this->openOnLoad = $b; return $this; }

    public function setAttr(string $name, string|int|float|bool $value): self { $this->attrs[$name] = $value; return $this; }
    public function addClass(string ...$classes): self
    { $this->attrs['class'] = $this->ensureClassStr((string)($this->attrs['class'] ?? ''), ...$classes); return $this; }

    public function title(string $text, ?string $desc = null): self
    { $this->title = $text; $this->desc = $desc; return $this; }

    public function headerActionsRaw(string $html): self { $this->headerActionsRaw = $html; return $this; }

    public function body(string $content, bool $raw = false): self { $this->bodyContent = $content; $this->bodyRaw = $raw; return $this; }

    public function footer(string $htmlRaw): self { $this->footerRaw = $htmlRaw; return $this; }

    /** Restituisce un pulsante trigger compatibile */
    public function triggerButton(string $label, array $attrs = []): string
    {
        $attrs['data-clmodal-open'] = $this->id ?: 'unknown';
        $attrs['type'] = $attrs['type'] ?? 'button';
        $attrs['class'] = $this->ensureClassStr((string)($attrs['class'] ?? ''), 'clmodal__trigger');
        return '<button'.$this->attrsToString($attrs).'>'.$this->esc($label).'</button>';
    }

    public function render(): string
    {
        $out = [];
        if ($this->includeDefaultCss && !self::$stylePrinted) { $out[] = $this->styleTag(); self::$stylePrinted = true; }
        if ($this->includeDefaultJs && !self::$scriptPrinted) { $out[] = $this->scriptTag(); self::$scriptPrinted = true; }

        $classes = [$this->variant === 'drawer' ? 'clmodal--drawer' : 'clmodal--modal', 'size-'.$this->size];
        if ($this->variant === 'drawer') $classes[] = 'side-'.$this->drawerSide;
        if (!$this->closable) $classes[] = 'not-closable';
        $this->addClass(...$classes);

        $aria = [
            'role' => 'dialog',
            'aria-modal' => 'true',
            'aria-labelledby' => $this->idTitle,
            'aria-describedby' => $this->idDesc,
            'data-clmodal' => $this->id,
        ];
        $wrapAttrs = array_replace($this->attrs, $aria);

        $out[] = '<div'.$this->attrsToString($wrapAttrs).'>';
        $out[] = '  <div class="clmodal__backdrop" data-clmodal-close></div>';
        $out[] = '  <div class="clmodal__wrap">';
        $out[] = '    <div class="clmodal__panel">';

        // Header
        $out[] = '      <div class="clmodal__header">';
        $out[] = '        <div class="clmodal__title-group">';
        if ($this->title !== null) {
            $out[] = '          <h2 id="'.$this->escAttr($this->idTitle).'" class="clmodal__title">'.$this->esc($this->title).'</h2>';
        }
        if ($this->desc !== null) {
            $out[] = '          <div id="'.$this->escAttr($this->idDesc).'" class="clmodal__desc">'.$this->esc($this->desc).'</div>';
        } else {
            // comunque presente per aria-describedby
            $out[] = '          <div id="'.$this->escAttr($this->idDesc).'" class="clmodal__desc" hidden></div>';
        }
        $out[] = '        </div>';

        $actions = $this->headerActionsRaw ?? '';
        $out[] = '        <div class="clmodal__actions">'.$actions.'<button type="button" class="clmodal__close" aria-label="Chiudi" data-clmodal-close>&times;</button></div>';
        $out[] = '      </div>';

        // Body
        $out[] = '      <div class="clmodal__body">'.($this->bodyRaw ? $this->bodyContent : $this->esc($this->bodyContent)).'</div>';

        // Footer
        if ($this->footerRaw !== null) {
            $out[] = '      <div class="clmodal__footer">'.$this->footerRaw.'</div>';
        }

        $out[] = '    </div>';
        $out[] = '  </div>';
        $out[] = '</div>';

        if ($this->openOnLoad) {
            $out[] = '<script>(function(){var m=document.querySelector("#'. $this->escAttr($this->id) .'"); if(m){ m.classList.add("is-open"); document.body.classList.add("clmodal-lock"); }})();</script>';
        }

        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    // ====== Utils ======
    protected function genId(): string
    {
        try { return 'clm-'.bin2hex(random_bytes(4)); } catch (\Throwable) { return uniqid('clm-', false); }
    }

    protected function ensureClass(string $cls): void { $this->attrs['class'] = $this->ensureClassStr((string)($this->attrs['class'] ?? ''), $cls); }

    protected function ensureClassStr(string $existing, string ...$add): string
    {
        $existing = trim(preg_replace('/\s+/', ' ', $existing) ?? '');
        $parts = $existing !== '' ? explode(' ', $existing) : [];
        foreach ($add as $cls) { if (!in_array($cls, $parts, true)) $parts[] = $cls; }
        return trim(implode(' ', $parts));
    }

    protected function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    protected function escAttr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    protected function attrsToString(array $attrs): string
    {
        if (empty($attrs)) return '';
        $parts = [];
        foreach ($attrs as $k=>$v) {
            if ($v === null) continue;
            if (is_bool($v)) { if ($v) $parts[] = $this->escAttr($k); continue; }
            $parts[] = $this->escAttr($k).'="'.$this->escAttr((string)$v).'"';
        }
        return $parts ? ' '.implode(' ', $parts) : '';
    }

    protected function styleTag(): string
    {
        $css = <<<CSS
        <style>
        /* ====== CLModal base ====== */
        :root{ --clm-radius:14px; --clm-shadow:0 20px 40px rgba(0,0,0,.20); --clm-bg:#fff; --clm-b:#e5e7eb; --clm-muted:#6b7280; }
        body.clmodal-lock{ overflow:hidden; }

        .clmodal{ position:fixed; inset:0; z-index:999; display:none; }
        .clmodal.is-open{ display:block; }
        .clmodal__backdrop{ position:fixed; inset:0; background:rgba(17,24,39,.5); opacity:0; transition:opacity .2s ease; }
        .clmodal.is-open .clmodal__backdrop{ opacity:1; }

        /* Wrap & panel */
        .clmodal__wrap{ position:relative; width:100%; height:100%; display:grid; align-items:center; justify-items:center; }
        .clmodal__panel{ background:var(--clm-bg); color:#111827; border:1px solid var(--clm-b); border-radius:var(--clm-radius); box-shadow:var(--clm-shadow); width: min(92vw, 920px); max-height: 86vh; display:flex; flex-direction:column; opacity:0; transform:translateY(8px) scale(.98); transition:transform .22s ease, opacity .22s ease; }
        .clmodal.is-open .clmodal__panel{ opacity:1; transform:none; }

        /* Sizes */
        .clmodal.size-sm .clmodal__panel{ width:min(92vw, 420px); }
        .clmodal.size-md .clmodal__panel{ width:min(92vw, 640px); }
        .clmodal.size-lg .clmodal__panel{ width:min(92vw, 800px); }
        .clmodal.size-xl .clmodal__panel{ width:min(92vw, 960px); }

        /* Drawer variant */
        .clmodal--drawer .clmodal__wrap{ align-items:stretch; justify-items:stretch; }
        .clmodal--drawer .clmodal__panel{ height:100%; max-height:none; border-radius:0; }
        .clmodal--drawer.side-right .clmodal__panel{ margin-left:auto; width:min(92vw, 520px); transform:translateX(24px); }
        .clmodal--drawer.side-left  .clmodal__panel{ margin-right:auto; width:min(92vw, 520px); transform:translateX(-24px); }
        .clmodal--drawer.side-bottom .clmodal__panel{ margin-top:auto; width:100%; transform:translateY(24px); }
        .clmodal--drawer.side-top    .clmodal__panel{ margin-bottom:auto; width:100%; transform:translateY(-24px); }
        .clmodal.is-open.clmodal--drawer .clmodal__panel{ transform:none; }

        /* Header/Body/Footer */
        .clmodal__header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #f1f5f9; }
        .clmodal__title{ font-size:16px; font-weight:700; color:#111827; }
        .clmodal__desc{ font-size:13px; color:var(--clm-muted); margin-top:2px; }
        .clmodal__actions{ display:flex; align-items:center; gap:8px; }
        .clmodal__close{ border:0; background:#f3f4f6; border-radius:10px; width:34px; height:34px; cursor:pointer; font-size:20px; line-height:1; color:#111827; }
        .clmodal__close:hover{ background:#e5e7eb; }

        .clmodal__body{ padding:16px 18px; overflow:auto; }
        .clmodal__footer{ padding:14px 18px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:8px; }

        /* Accessibility helpers */
        .clmodal [hidden]{ display:none !important; }
        </style>
        CSS;
                return $css;
            }

    protected function scriptTag(): string
    {
        $js = <<<JS
        <script>
        (function(){
        if(window.__clmodal_init) return; window.__clmodal_init = true;
        var openClass = 'is-open';
        var bodyLockClass = 'clmodal-lock';

        function qs(sel, root){ return (root||document).querySelector(sel); }
        function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

        function getFocusable(root){
            return qsa('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])', root)
            .filter(function(el){ return el.offsetParent !== null || el === document.activeElement; });
        }

        function openModal(mod){
            if(!mod) return; if(mod.classList.contains(openClass)) return;
            mod.classList.add(openClass);
            document.body.classList.add(bodyLockClass);
            // focus trap
            var panel = qs('.clmodal__panel', mod);
            var f = getFocusable(panel); if(f.length){ f[0].focus(); }
            mod.__prevFocus = document.activeElement;
        }
        function closeModal(mod){
            if(!mod) return; if(!mod.classList.contains(openClass)) return;
            mod.classList.remove(openClass);
            // unlock body solo se nessuna altra modale è aperta
            if(!qs('.clmodal.'+openClass)) document.body.classList.remove(bodyLockClass);
            if(mod.__prevFocus && typeof mod.__prevFocus.focus === 'function') try{ mod.__prevFocus.focus(); }catch(e){}
        }

        // Delegation: open by [data-clmodal-open]
        document.addEventListener('click', function(e){
            var t = e.target.closest('[data-clmodal-open]');
            if(!t) return;
            var id = t.getAttribute('data-clmodal-open');
            var mod = id ? document.getElementById(id) : null;
            if(mod){ e.preventDefault(); openModal(mod); }
        });

        // Delegation: close by [data-clmodal-close]
        document.addEventListener('click', function(e){
            var t = e.target.closest('[data-clmodal-close]');
            if(!t) return;
            var mod = e.target.closest('.clmodal');
            if(mod){ e.preventDefault(); if(!mod.classList.contains('not-closable')) closeModal(mod); }
        });

        // Close on ESC
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
            var open = document.querySelector('.clmodal.'+openClass);
            if(open && !open.classList.contains('not-closable')) closeModal(open);
            }
        });

        // Click on backdrop should close (already handled by data-clmodal-close on backdrop)

        // Focus trap cycling
        document.addEventListener('keydown', function(e){
            if(e.key !== 'Tab') return;
            var open = document.querySelector('.clmodal.'+openClass); if(!open) return;
            var panel = qs('.clmodal__panel', open); var f = getFocusable(panel); if(!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if(e.shiftKey && document.activeElement === first){ last.focus(); e.preventDefault(); }
            else if(!e.shiftKey && document.activeElement === last){ first.focus(); e.preventDefault(); }
        });
        })();
        </script>
        JS;
        return $js;
    }
}
