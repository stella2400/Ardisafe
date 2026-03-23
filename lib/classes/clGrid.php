<?php
/**
 * CLGrid.php — v1.1
 *
 * Griglia responsive 12-colonne (stile framework) senza HTML manuale.
 * - CSS integrato (una sola volta) — disattivabile con ->noDefaultCss()
 * - Container opzionale con max-width (sm/md/lg/xl)
 * - Row con gutter variabile, wrap/nowrap, allineamenti (justify/align)
 * - Colonne: span di base + varianti responsive: sm|md|lg|xl (1..12)
 * - Offset: offset, sm-offset, md-offset, lg-offset, xl-offset (in colonne)
 * - Order: order, sm-order, md-order, lg-order, xl-order
 * - Align “self” per singola colonna: self=start|center|end|stretch
 * - Nuovo: colRaw() per inserire HTML non-escapato senza dover ricordare 'raw'=>true
 * - API fluente: start()->container()->row()->col(...)->col(...)->row()->...->render()
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLGrid
{
    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    /** @var array<string,mixed> */
    protected array $wrapperAttrs = ['class' => 'clgrid'];

    /** @var array<int, array{opts:array, cols:array<int,array>}> */
    protected array $rows = [];
    protected ?int $currentRow = null;

    // ===== Config base =====
    public function start(array $attrs = []): self
    {
        $this->wrapperAttrs = array_replace($this->wrapperAttrs, $attrs);
        $this->ensureClass('clgrid');
        return $this;
    }

    public function container(string $max = 'xl'): self
    {
        $this->ensureClass('clgrid--container');
        $allowed = ['sm','md','lg','xl'];
        $max = in_array($max, $allowed, true) ? $max : 'xl';
        // rimuovi eventuali max-* precedenti e aggiungi quello nuovo
        $this->wrapperAttrs['class'] = preg_replace('/\bmax-(sm|md|lg|xl)\b/', '', (string)$this->wrapperAttrs['class']);
        $this->ensureClass('max-'.$max);
        return $this;
    }

    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    // ===== Row =====
    /**
     * Avvia una nuova riga.
     * $opts:
     *  - g (int px) gutter orizzontale (default 16)
     *  - nowrap (bool) default false
     *  - justify: start|center|end|between (default start)
     *  - align: start|center|end|stretch (default stretch)
     *  - class, attrs (array)
     */
    public function row(array $opts = []): self
    {
        $opts = array_replace([
            'g' => 16,
            'nowrap' => false,
            'justify' => 'start',
            'align' => 'stretch',
            'class' => '',
            'attrs' => [],
        ], $opts);

        // prepara classi utility per la row
        $classes = trim((string)$opts['class']);
        $classes = $this->ensureClassStr($classes, 'clrow');
        if (!empty($opts['nowrap'])) $classes = $this->ensureClassStr($classes, 'nowrap');

        $just = ['start','center','end','between'];
        $algn = ['start','center','end','stretch'];
        $classes = $this->ensureClassStr($classes, 'jc-'.(in_array($opts['justify'], $just, true) ? $opts['justify'] : 'start'));
        $classes = $this->ensureClassStr($classes, 'ai-'.(in_array($opts['align'],   $algn, true) ? $opts['align']   : 'stretch'));

        $attrs = (array)$opts['attrs'];
        $attrs['class'] = $classes;
        // gutter via CSS var
        $g = (int)$opts['g'];
        if ($g !== 16) {
            $attrs['style'] = trim(($attrs['style'] ?? '') . ';--g:'.$g.'px');
        }

        $this->rows[] = ['opts' => $opts, 'cols' => []];
        $this->currentRow = count($this->rows) - 1;
        // salva gli attrs sulla row stessa
        $this->rows[$this->currentRow]['opts']['_attrs'] = $attrs;
        return $this;
    }

    /**
     * Aggiunge una colonna.
     * @param int $span 1..12 (base mobile-first)
     * @param array $opts:
     *   - sm|md|lg|xl => int 1..12 (override responsive)
     *   - offset|sm-offset|md-offset|lg-offset|xl-offset => int 0..11
     *   - order|sm-order|md-order|lg-order|xl-order => int (-10..10 consigliato)
     *   - self => start|center|end|stretch (align-self)
     *   - class => string
     *   - attrs => array
     *   - raw => bool (se true non esegue escaping su $content)
     */
    public function col(int $span, array $opts = [], ?string $content = null): self
    {
        if ($this->currentRow === null) {
            // se non c’è una riga aperta, creane una di default
            $this->row();
        }
        $span = max(1, min(12, (int)$span));
        $opts = array_replace([
            'class' => '',
            'attrs' => [],
            'raw'   => false,
        ], $opts);

        $col = [
            'span'    => $span,
            'opts'    => $opts,
            'content' => $content ?? '',
        ];
        $this->rows[$this->currentRow]['cols'][] = $col;
        return $this;
    }

    /** Shortcut: inserisce direttamente HTML non-escapato in colonna. */
    public function colRaw(int $span, array $opts = [], string $html = ''): self
    {
        $opts['raw'] = true;
        return $this->col($span, $opts, $html);
    }

    /** Inserisce uno “spacer” vuoto (utile come offset visivo complesso). */
    public function spacer(int $span, array $opts = []): self
    {
        return $this->col($span, array_replace($opts, ['class'=>($opts['class'] ?? '').' is-spacer']), '');
    }

    /** Chiude la riga corrente (facoltativo). */
    public function endRow(): self { $this->currentRow = null; return $this; }

    // ===== Render =====
    public function render(): string
    {
        $out = [];
        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        $out[] = '<div' . $this->attrsToString($this->wrapperAttrs) . '>';

        foreach ($this->rows as $row) {
            $rowAttrs = $row['opts']['_attrs'] ?? ['class' => 'clrow'];
            $out[] = '  <div' . $this->attrsToString($rowAttrs) . '>';
            foreach ($row['cols'] as $col) {
                $out[] = '    ' . $this->renderCol($col);
            }
            $out[] = '  </div>';
        }

        $out[] = '</div>';
        return implode("\n", $out);
    }

    public function __toString(): string
    {
        try { return $this->render(); } catch (\Throwable) { return ''; }
    }

    // ===== Interni =====
    protected function renderCol(array $col): string
    {
        $span = (int)$col['span'];
        /** @var array<string,mixed> $opts */
        $opts = $col['opts'];
        $content = (string)$col['content'];
        $raw = !empty($opts['raw']);

        // classi di base
        $classes = $this->ensureClassStr((string)($opts['class'] ?? ''), 'clcol');
        $classes = $this->ensureClassStr($classes, 'c-'.$span);

        // responsive spans
        foreach (['sm','md','lg','xl'] as $bp) {
            if (isset($opts[$bp])) {
                $v = max(1, min(12, (int)$opts[$bp]));
                $classes = $this->ensureClassStr($classes, $bp.'-'.$v);
            }
        }

        // offset
        foreach (['','sm','md','lg','xl'] as $bp) {
            $key = $bp ? ($bp.'-offset') : 'offset';
            if (isset($opts[$key])) {
                $v = max(0, min(11, (int)$opts[$key]));
                $classes = $this->ensureClassStr($classes, ($bp ? $bp.'-' : '').'offset-'.$v);
            }
        }

        // order
        foreach (['','sm','md','lg','xl'] as $bp) {
            $key = $bp ? ($bp.'-order') : 'order';
            if (isset($opts[$key])) {
                $v = (int)$opts[$key];
                $classes = $this->ensureClassStr($classes, ($bp ? $bp.'-' : '').'order-'.$v);
            }
        }

        // self align
        if (!empty($opts['self']) && in_array($opts['self'], ['start','center','end','stretch'], true)) {
            $classes = $this->ensureClassStr($classes, 'self-'.$opts['self']);
        }

        $attrs = (array)($opts['attrs'] ?? []);
        $attrs['class'] = trim($classes);

        $html = '<div' . $this->attrsToString($attrs) . '>';
        $html .= $raw ? $content : $this->esc($content);
        $html .= '</div>';
        return $html;
    }

    protected function ensureClass(string $cls): void
    {
        $this->wrapperAttrs['class'] = $this->ensureClassStr((string)($this->wrapperAttrs['class'] ?? ''), $cls);
    }

    protected function ensureClassStr(string $existing, string $add): string
    {
        $existing = trim(preg_replace('/\s+/', ' ', $existing) ?? '');
        $parts = $existing !== '' ? explode(' ', $existing) : [];
        if (!in_array($add, $parts, true)) $parts[] = $add;
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
            $parts[] = $this->escAttr($k) . '="' . $this->escAttr((string)$v) . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    protected function styleTag(): string
    {
        // Costruiamo CSS dinamico per le 12 colonne + responsive + offset + order
        $bp = [
            'sm' => 640,
            'md' => 768,
            'lg' => 1024,
            'xl' => 1280,
        ];
        $css = [];
        $css[] = '<style>';
        $css[] = '/* ====== CLGrid base ====== */';
        $css[] = '.clgrid{box-sizing:border-box;}';
        $css[] = '.clgrid--container{width:100%;margin-left:auto;margin-right:auto;padding-left:16px;padding-right:16px;}';
        $css[] = '.clgrid--container.max-sm{max-width:640px;}';
        $css[] = '.clgrid--container.max-md{max-width:768px;}';
        $css[] = '.clgrid--container.max-lg{max-width:1024px;}';
        $css[] = '.clgrid--container.max-xl{max-width:1280px;}';

        // Row
        $css[] = '.clrow{display:flex;flex-wrap:wrap;box-sizing:border-box;margin-left:calc(-1*var(--g,16px)/2);margin-right:calc(-1*var(--g,16px)/2);}';
        $css[] = '.clrow.nowrap{flex-wrap:nowrap;}';
        $css[] = '.clrow>.clcol{box-sizing:border-box;padding-left:calc(var(--g,16px)/2);padding-right:calc(var(--g,16px)/2);}';
        // Justify
        $css[] = '.clrow.jc-start{justify-content:flex-start;}';
        $css[] = '.clrow.jc-center{justify-content:center;}';
        $css[] = '.clrow.jc-end{justify-content:flex-end;}';
        $css[] = '.clrow.jc-between{justify-content:space-between;}';
        // Align items
        $css[] = '.clrow.ai-start{align-items:flex-start;}';
        $css[] = '.clrow.ai-center{align-items:center;}';
        $css[] = '.clrow.ai-end{align-items:flex-end;}';
        $css[] = '.clrow.ai-stretch{align-items:stretch;}';

        // Col: base 100% + self align + order/offset generici
        $css[] = '.clcol{flex:0 0 auto;width:100%;}';
        $css[] = '.clcol.self-start{align-self:flex-start;}';
        $css[] = '.clcol.self-center{align-self:center;}';
        $css[] = '.clcol.self-end{align-self:flex-end;}';
        $css[] = '.clcol.self-stretch{align-self:stretch;}';
        $css[] = '.clcol.is-spacer{min-height:1px;}'; // spacer invisibile

        // % helper
        $percent = fn(int $n) => rtrim(rtrim(number_format(100*$n/12, 6, '.', ''), '0'), '.').'%';

        // width classes c-1..12
        for ($i=1; $i<=12; $i++) {
            $css[] = ".c-$i{width:".$percent($i).";}";
        }
        // offset-0..11
        for ($i=0; $i<=11; $i++) {
            $css[] = ".offset-$i{margin-left:".$percent($i).";}";
        }
        // order generico da -10 a 10
        for ($i=-10; $i<=10; $i++) {
            $css[] = ".order-$i{order:$i;}";
        }

        // Breakpoint responsive: sm|md|lg|xl
        foreach ($bp as $name => $min) {
            $css[] = "@media (min-width: {$min}px){";
            for ($i=1; $i<=12; $i++) {
                $css[] = ".{$name}-$i{width:".$percent($i).";}";
            }
            for ($i=0; $i<=11; $i++) {
                $css[] = ".{$name}-offset-$i{margin-left:".$percent($i).";}";
            }
            for ($i=-10; $i<=10; $i++) {
                $css[] = ".{$name}-order-$i{order:$i;}";
            }
            $css[] = "}";
        }

        $css[] = '</style>';
        return implode("\n", $css);
    }
}
