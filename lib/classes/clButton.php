<?php
/**
 * CLButton.php — v1.0
 *
 * Builder per pulsanti singoli o gruppi, senza HTML manuale.
 * - CSS integrato (una sola volta per request) — disattivabile
 * - Varianti: primary, secondary, outline, ghost, success, danger
 * - Tagli: sm, md, lg
 * - Icone sinistra/destra (HTML raw opzionale), stato loading con spinner
 * - Pulsanti <button>, <a role="button">, <input type="submit/reset/button">
 * - Gruppi orizzontali/verticali, spacing e merging border
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLButton
{
    /** @var array<int, array<string,mixed>> */
    protected array $items = []; // pulsanti accumulati se si usa startGroup()
    protected bool $groupMode = false;
    /** @var array<string,mixed> */
    protected array $groupAttrs = [];

    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    /** ====== Config globale ====== */
    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    /** ====== Gruppi ====== */

    /**
     * Avvia un gruppo di pulsanti.
     * @param array<string,mixed> $attrs es. ['class'=>'my-group', 'data-role'=>'toolbar', 'vertical'=>true]
     */
    public function startGroup(array $attrs = []): self
    {
        $this->groupMode = true;
        $this->groupAttrs = array_replace(['class' => 'clbtn-group', 'vertical' => false, 'merge' => true], $attrs);
        $this->groupAttrs['class'] = $this->ensureClass($this->groupAttrs['class'] ?? '', 'clbtn-group');
        return $this;
    }

    /** Shortcut: aggiunge HTML raw dentro al gruppo (divider, ecc.) */
    public function rawHtml(string $html): self
    {
        $this->items[] = ['kind' => 'raw', 'html' => $html];
        return $this;
    }

    /** ====== Pulsanti ====== */

    /**
     * Aggiunge un pulsante "button".
     * @param array<string,mixed> $opts vedi doc blocco sotto
     */
    public function button(string $text, array $opts = []): self
    {
        return $this->addItem('button', $text, $opts);
    }

    /** Pulsante "submit" (button type=submit) */
    public function submit(string $text = 'Invia', array $opts = []): self
    {
        $opts['type'] = 'submit';
        return $this->addItem('button', $text, $opts);
    }

    /** Pulsante "reset" */
    public function reset(string $text = 'Reset', array $opts = []): self
    {
        $opts['type'] = 'reset';
        return $this->addItem('button', $text, $opts);
    }

    /**
     * Link-as-button <a role="button">
     * @param array<string,mixed> $opts come button(), più 'href','target','rel'
     */
    public function link(string $text, string $href, array $opts = []): self
    {
        $opts['as'] = 'a';
        $opts['href'] = $href;
        return $this->addItem('button', $text, $opts);
    }

    /**
     * Restituisce direttamente un singolo pulsante (senza gruppo).
     * È comodo per echo rapido: (new CLButton())->single('Salva', [...])
     */
    public function single(string $text, array $opts = []): string
    {
        $this->items = [];
        $this->groupMode = false;
        $this->addItem('button', $text, $opts);
        return $this->render();
    }

    /**
     * Opzioni comuni per button()/link()/submit()/reset():
     *  - 'variant'  => 'primary'|'secondary'|'outline'|'ghost'|'success'|'danger' (default 'primary')
     *  - 'size'     => 'sm'|'md'|'lg' (default 'md')
     *  - 'full'     => bool (full width) - aggiunge classe .clbtn--block
     *  - 'disabled' => bool
     *  - 'loading'  => bool (mostra spinner e disabilita)
     *  - 'attrs'    => array attributi extra per il <button>/<a>
     *  - 'iconLeft'  => string HTML raw per icona a sinistra (opzionale)
     *  - 'iconRight' => string HTML raw per icona a destra (opzionale)
     *  - 'type'     => 'button'|'submit'|'reset' (solo per <button>)
     *  - 'as'       => 'button' (default) | 'a' | 'input' (usa 'type' + 'value')
     *  - 'href','target','rel' (solo quando 'as' = 'a')
     */
    protected function addItem(string $kind, string $text, array $opts): self
    {
        $opts = array_replace([
            'variant' => 'primary',
            'size' => 'md',
            'full' => false,
            'disabled' => false,
            'loading' => false,
            'attrs' => [],
            'iconLeft' => null,
            'iconRight' => null,
            'type' => 'button',
            'as' => 'button', // 'button' | 'a' | 'input'
        ], $opts);

        $this->items[] = [
            'kind'  => $kind,
            'text'  => $text,
            'opts'  => $opts,
        ];
        return $this;
    }

    /** ====== Render ====== */

    public function render(): string
    {
        $out = [];

        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        if ($this->groupMode) {
            $classes = (string)($this->groupAttrs['class'] ?? 'clbtn-group');
            if (!empty($this->groupAttrs['vertical'])) $classes = $this->ensureClass($classes, 'clbtn-group--vertical');
            if (!empty($this->groupAttrs['merge']))    $classes = $this->ensureClass($classes, 'clbtn-group--merge');

            $attrs = $this->groupAttrs;
            $attrs['class'] = $classes;
            unset($attrs['vertical'], $attrs['merge']);

            $out[] = '<div' . $this->attrsToString($attrs) . '>';
            foreach ($this->items as $item) {
                $out[] = $this->renderItem($item);
            }
            $out[] = '</div>';
        } else {
            // se non è gruppo: restituiamo il singolo/stack
            foreach ($this->items as $item) {
                $out[] = $this->renderItem($item);
            }
        }

        return implode("\n", $out);
    }

    public function __toString(): string
    {
        try { return $this->render(); } catch (\Throwable) { return ''; }
    }

    /** ====== Helpers ====== */

    protected function renderItem(array $item): string
    {
        if ($item['kind'] === 'raw') {
            return (string)$item['html'];
        }

        /** @var array<string,mixed> $o */
        $o = $item['opts'];
        $variant = $this->sanitizeVariant((string)$o['variant']);
        $size = $this->sanitizeSize((string)$o['size']);
        $classes = "clbtn clbtn--{$variant} clbtn--{$size}";
        if (!empty($o['full']))    $classes .= ' clbtn--block';
        if (!empty($o['loading'])) $classes .= ' is-loading';

        // Attributi base
        $attrs = array_replace([
            'class' => $classes,
            'title' => $o['attrs']['title'] ?? null
        ], (array)$o['attrs']);

        $iconLeft  = (string)($o['iconLeft']  ?? '');
        $iconRight = (string)($o['iconRight'] ?? '');
        $label = $this->esc((string)$item['text']);
        $inner = $this->buttonInner($iconLeft, $label, $iconRight, !empty($o['loading']));

        $as = (string)$o['as'];

        // Disabled / loading handling
        $isDisabled = !empty($o['disabled']) || !empty($o['loading']);

        if ($as === 'a') {
            $attrs['role'] = $attrs['role'] ?? 'button';
            $attrs['href'] = $o['href'] ?? '#';
            if ($isDisabled) {
                // Aria-disabled per link, e tabindex -1
                $attrs['aria-disabled'] = 'true';
                $attrs['tabindex'] = '-1';
                // rimuovo href se disabilitato per evitare navigazione
                unset($attrs['href']);
            }
            if (!empty($o['target'])) $attrs['target'] = $o['target'];
            if (!empty($o['rel']))    $attrs['rel'] = $o['rel'];

            return '<a' . $this->attrsToString($attrs) . '>' . $inner . '</a>';
        }

        if ($as === 'input') {
            // input type=submit/reset/button con value=label (icone non sono applicabili qui)
            $type = in_array(($o['type'] ?? 'button'), ['submit','reset','button'], true) ? $o['type'] : 'button';
            $attrs['type'] = $type;
            $attrs['value'] = $this->escAttr((string)$item['text']);
            if ($isDisabled) $attrs['disabled'] = true;
            // niente innerHTML per input
            return '<input' . $this->attrsToString($attrs) . ' />';
        }

        // Default: <button>
        $type = in_array(($o['type'] ?? 'button'), ['submit','reset','button'], true) ? $o['type'] : 'button';
        $attrs['type'] = $type;
        if ($isDisabled) $attrs['disabled'] = true;
        if (!empty($o['loading'])) $attrs['data-loading'] = 'true';

        return '<button' . $this->attrsToString($attrs) . '>' . $inner . '</button>';
    }

    protected function buttonInner(string $iconLeft, string $labelEscaped, string $iconRight, bool $loading): string
    {
        $parts = [];
        $parts[] = '<span class="clbtn__inner">';
        if ($loading) {
            $parts[] = '  <span class="clbtn__spinner" aria-hidden="true"></span>';
        }
        if ($iconLeft !== '') {
            // icone raw: l'HTML viene inserito così com'è
            $parts[] = '  <span class="clbtn__icon clbtn__icon--left">' . $iconLeft . '</span>';
        }
        $parts[] = '  <span class="clbtn__label">' . $labelEscaped . '</span>';
        if ($iconRight !== '') {
            $parts[] = '  <span class="clbtn__icon clbtn__icon--right">' . $iconRight . '</span>';
        }
        $parts[] = '</span>';
        return implode("\n", $parts);
    }

    protected function sanitizeVariant(string $v): string
    {
        $allowed = ['primary','secondary','outline','ghost','success','danger'];
        return in_array($v, $allowed, true) ? $v : 'primary';
    }

    protected function sanitizeSize(string $s): string
    {
        $allowed = ['sm','md','lg'];
        return in_array($s, $allowed, true) ? $s : 'md';
    }

    protected function ensureClass(string $existing, string $add): string
    {
        $existing = trim(preg_replace('/\s+/', ' ', $existing) ?? '');
        $classes = array_filter(explode(' ', $existing));
        if (!in_array($add, $classes, true)) $classes[] = $add;
        return implode(' ', $classes);
    }

    protected function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    protected function escAttr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /**
     * @param array<string,mixed> $attrs
     */
    protected function attrsToString(array $attrs): string
    {
        if (empty($attrs)) return '';
        $parts = [];
        foreach ($attrs as $k => $v) {
            if ($v === null) continue;
            if (is_bool($v)) { if ($v) $parts[] = $this->escAttr($k); continue; }
            $parts[] = $this->escAttr($k) . '="' . $this->escAttr((string)$v) . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /** CSS integrato */
    protected function styleTag(): string
    {
        $css = <<<CSS
        <style>
        /* ====== CLButton base ====== */
        .clbtn{--c-bg:#111827; --c-fg:#ffffff; --c-border:#111827; --c-hover:#0b1220; --c-focus:rgba(37,99,235,.2);
        --radius:10px; --pad-y:10px; --pad-x:14px; --gap:8px; --font:14px;
        appearance:none; display:inline-flex; align-items:center; justify-content:center; gap:var(--gap);
        border:1px solid var(--c-border); background:var(--c-bg); color:var(--c-fg);
        padding:var(--pad-y) var(--pad-x); border-radius:var(--radius);
        text-decoration:none; cursor:pointer; line-height:1.2; white-space:nowrap; user-select:none;
        transition:background .15s ease, border-color .15s ease, transform .02s ease;
        }
        .clbtn:focus{outline:none; box-shadow:0 0 0 3px var(--c-focus);}
        .clbtn:hover{background:var(--c-hover);}
        .clbtn:active{transform:translateY(1px);}
        .clbtn[disabled], .clbtn[aria-disabled="true"]{opacity:.6; cursor:not-allowed; pointer-events:none;}
        .clbtn--block{display:flex; width:100%;}
        /* sizes */
        .clbtn--sm{--font:12px; --pad-y:8px; --pad-x:12px; --gap:6px; font-size:var(--font);}
        .clbtn--md{--font:14px; --pad-y:10px; --pad-x:14px; font-size:var(--font);}
        .clbtn--lg{--font:16px; --pad-y:12px; --pad-x:18px; --gap:10px; font-size:var(--font);}
        .clbtn__inner{display:inline-flex; align-items:center; gap:var(--gap);}
        .clbtn__icon{display:inline-flex;}
        .clbtn__spinner{width:1em; height:1em; border-radius:999px; border:2px solid currentColor; border-right-color:transparent; margin-right:4px; animation:clspin .8s linear infinite;}
        @keyframes clspin {to { transform: rotate(360deg); } }
        .is-loading .clbtn__label{opacity:.9}
        /* variants */
        .clbtn--primary{--c-bg:#111827; --c-fg:#fff; --c-border:#111827; --c-hover:#0f172a;}
        .clbtn--secondary{--c-bg:#ffffff; --c-fg:#111827; --c-border:#e5e7eb; --c-hover:#f3f4f6;}
        .clbtn--outline{--c-bg:transparent; --c-fg:#111827; --c-border:#d1d5db; --c-hover:#f9fafb;}
        .clbtn--ghost{--c-bg:transparent; --c-fg:#111827; --c-border:transparent; --c-hover:#f3f4f6;}
        .clbtn--success{--c-bg:#065f46; --c-fg:#ecfdf5; --c-border:#065f46; --c-hover:#064e3b;}
        .clbtn--danger{--c-bg:#b91c1c; --c-fg:#fff5f5; --c-border:#b91c1c; --c-hover:#991b1b;}
        /* gruppi */
        .clbtn-group{display:inline-flex; gap:8px;}
        .clbtn-group--vertical{flex-direction:column; align-items:stretch;}
        .clbtn-group--merge{gap:0;}
        .clbtn-group--merge > .clbtn{border-radius:0;}
        .clbtn-group--merge > .clbtn:first-child{border-top-left-radius:10px; border-bottom-left-radius:10px;}
        .clbtn-group--merge > .clbtn:last-child{border-top-right-radius:10px; border-bottom-right-radius:10px;}
        .clbtn-group--merge > .clbtn + .clbtn{margin-left:-1px;} /* unisci bordi */
        </style>
        CSS;
        return $css;
    }
}
