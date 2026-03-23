<?php
/**
 * CLTable.php — v1.2
 * Tabella HTML con CSS integrato opzionale.
 * - CSS predefinito incluso automaticamente (una volta sola) -> theme('zebra'|'minimal'|'boxed')
 * - Disattivabile con noDefaultCss()
 * - API fluente: start()->header()->row()->footer()->render()
 * - Colonne “raw” (non-escaped) con rawCols([indici_colonne])
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLTable
{
    /** @var array<string, mixed> */
    protected array $tableAttrs = [];
    protected bool $started = false;

    /** sezioni */
    protected array $thead = [];
    protected array $tbody = [];
    protected array $tfoot = [];
    protected array $colgroup = [];
    protected ?array $caption = null;

    /** CSS integrato */
    protected bool $includeDefaultCss = true;
    protected string $theme = 'zebra'; // 'zebra' | 'minimal' | 'boxed'

    /** evita di stampare più volte lo <style> in stessa request */
    protected static bool $stylePrinted = false;

    /** colonne (0-based) da NON escapare nel tbody/tfoot */
    protected array $rawCols = [];

    /**
     * Avvia il builder con eventuali attributi della <table>.
     * @param array<string,mixed> $attrs
     */
    public function start(array $attrs = []): self
    {
        $this->tableAttrs = $attrs;
        $this->started = true;

        // garantiamo classi base per applicare il CSS integrato
        $this->addClass('cltable', "cltable--{$this->theme}");
        return $this;
    }

    /** Abilita/Disabilita CSS integrato */
    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    /** Cambia tema: 'zebra' | 'minimal' | 'boxed' (chiamare prima di render()) */
    public function theme(string $theme): self
    {
        $allowed = ['zebra','minimal','boxed'];
        if (!in_array($theme, $allowed, true)) return $this;
        // rimuovi vecchia modifier class se già presente (ora include anche 'boxed')
        if (!empty($this->tableAttrs['class'])) {
            $this->tableAttrs['class'] = preg_replace('/\bcltable--(zebra|minimal|boxed)\b/', '', (string)$this->tableAttrs['class']);
        }
        $this->theme = $theme;
        $this->addClass("cltable--{$theme}");
        return $this;
    }

    /** Aggiunge classi custom */
    public function addClass(string ...$classes): self
    {
        $existing = trim((string)($this->tableAttrs['class'] ?? ''));
        $merged = trim($existing . ' ' . implode(' ', $classes));
        if ($merged !== '') {
            $this->tableAttrs['class'] = preg_replace('/\s+/', ' ', $merged);
        }
        return $this;
    }

    /** Attributi generici / data-* */
    public function setAttr(string $name, string|int|float|bool $value): self { $this->tableAttrs[$name] = $value; return $this; }
    public function data(string $name, string|int|float|bool $value): self { $this->tableAttrs['data-' . $name] = $value; return $this; }

    /** Imposta colonne da non-escapare (0-based) nel tbody/tfoot */
    public function rawCols(array $idx): self
    {
        $this->rawCols = array_values(array_filter(
            array_map('intval', $idx),
            static fn(int $i) => $i >= 0
        ));
        return $this;
    }

    /** Caption, colgroup */
    public function caption(string $text, array $attrs = []): self { $this->caption = ['text'=>$text,'attrs'=>$attrs]; return $this; }
    public function colgroup(array $cols): self { $this->colgroup = array_map(fn($a)=>['attrs'=>$a], $cols); return $this; }

    /** Header / Row / Footer */
    public function header(array $columns, array $rowAttrs = [], array $defaultCellAttrs = []): self
    {
        $this->ensureStarted();
        $cells = array_map(fn($c)=>$this->normalizeCell($c,'th',$defaultCellAttrs), $columns);
        $this->thead[] = ['cells'=>$cells,'attrs'=>$rowAttrs];
        return $this;
    }

    public function row(array $cells, array $rowAttrs = [], array $defaultCellAttrs = []): self
    {
        $this->ensureStarted();

        // Normalizza e applica raw sulle colonne indicate
        $norm = [];
        $i = 0;
        foreach (array_values($cells) as $c) {
            $cell = $this->normalizeCell($c, 'td', $defaultCellAttrs);
            if (in_array($i, $this->rawCols, true)) { $cell['raw'] = true; }
            $norm[] = $cell;
            $i++;
        }

        $this->tbody[] = ['cells'=>$norm,'attrs'=>$rowAttrs];
        return $this;
    }

    public function footer(array $cells = [], array $rowAttrs = [], array $defaultCellAttrs = []): self
    {
        $this->ensureStarted();

        $norm = [];
        $i = 0;
        foreach (array_values($cells) as $c) {
            $cell = $this->normalizeCell($c, 'td', $defaultCellAttrs);
            if (in_array($i, $this->rawCols, true)) { $cell['raw'] = true; }
            $norm[] = $cell;
            $i++;
        }

        $this->tfoot[] = ['cells'=>$norm,'attrs'=>$rowAttrs];
        return $this;
    }

    /** Render completo (stampa anche lo <style> una sola volta se abilitato) */
    public function render(): string
    {
        $this->ensureStarted();

        $out = [];

        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        $out[] = '<table' . $this->attrsToString($this->tableAttrs) . '>';

        if ($this->caption !== null) {
            $out[] = '  <caption' . $this->attrsToString($this->caption['attrs']) . '>' . $this->esc($this->caption['text']) . '</caption>';
        }

        if (!empty($this->colgroup)) {
            $out[] = '  <colgroup>';
            foreach ($this->colgroup as $col) $out[] = '    <col' . $this->attrsToString($col['attrs']) . ' />';
            $out[] = '  </colgroup>';
        }

        if (!empty($this->thead)) {
            $out[] = '  <thead>';
            foreach ($this->thead as $row) {
                $out[] = '    <tr' . $this->attrsToString($row['attrs']) . '>';
                foreach ($row['cells'] as $cell) $out[] = $this->renderCell($cell,'th',6);
                $out[] = '    </tr>';
            }
            $out[] = '  </thead>';
        }

        $out[] = '  <tbody>';
        foreach ($this->tbody as $row) {
            $out[] = '    <tr' . $this->attrsToString($row['attrs']) . '>';
            foreach ($row['cells'] as $cell) $out[] = $this->renderCell($cell,'td',6);
            $out[] = '    </tr>';
        }
        $out[] = '  </tbody>';

        if (!empty($this->tfoot)) {
            $out[] = '  <tfoot>';
            foreach ($this->tfoot as $row) {
                $out[] = '    <tr' . $this->attrsToString($row['attrs']) . '>';
                foreach ($row['cells'] as $cell) $out[] = $this->renderCell($cell,'td',6);
                $out[] = '    </tr>';
            }
            $out[] = '  </tfoot>';
        }

        $out[] = '</table>';

        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    // ================= helpers =================
    protected function ensureStarted(): void
    {
        if (!$this->started) throw new \LogicException('Chiama start() prima di aggiungere header/row/footer.');
    }

    protected function normalizeCell(string|array|int|float $cell, string $defaultTag='td', array $defaultCellAttrs=[]): array
    {
        if (is_string($cell) || is_numeric($cell)) {
            return ['text'=>(string)$cell,'attrs'=>$defaultCellAttrs,'raw'=>false,'tag'=>$defaultTag];
        }
        $text  = isset($cell['text']) ? (string)$cell['text'] : '';
        $attrs = isset($cell['attrs']) && is_array($cell['attrs'])
            ? array_replace($defaultCellAttrs, $cell['attrs'])
            : $defaultCellAttrs;
        $raw = (bool)($cell['raw'] ?? false);
        $tag = (string)($cell['tag'] ?? $defaultTag);
        if ($tag !== 'td' && $tag !== 'th') $tag = $defaultTag;
        return ['text'=>$text,'attrs'=>$attrs,'raw'=>$raw,'tag'=>$tag];
    }

    protected function attrsToString(array $attrs): string
    {
        if (empty($attrs)) return '';
        $parts = [];
        foreach ($attrs as $k=>$v) {
            if (is_bool($v)) { if ($v) $parts[] = $this->escAttr($k); continue; }
            $parts[] = $this->escAttr($k) . '="' . $this->escAttr((string)$v) . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    protected function renderCell(array $cell, string $fallbackTag, int $indent=0): string
    {
        $tag = $cell['tag'] ?: $fallbackTag;
        $content = $cell['raw'] ? (string)$cell['text'] : $this->esc($cell['text']);
        return str_repeat(' ', $indent) . sprintf('<%1$s%2$s>%3$s</%1$s>', $tag, $this->attrsToString($cell['attrs']), $content);
    }

    protected function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    protected function escAttr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /** CSS incorporato per i temi disponibili */
    protected function styleTag(): string
    {
        $css = <<<CSS
<style>
/* ====== CLTable: base ====== */
.cltable{width:100%; border-collapse:collapse; border-spacing:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; font-size:14px; line-height:1.4; background:#fff; overflow:hidden; border:1px solid #e5e7eb; border-radius:8px; display:table;}
.cltable caption{caption-side:top; text-align:left; font-weight:600; padding:10px 12px; color:#374151;}
.cltable thead th{font-weight:600; text-align:left;}
.cltable th, .cltable td{padding:10px 12px; vertical-align:middle; border-bottom:1px solid #f0f0f0; max-width:0; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;}
.cltable tfoot td{font-weight:600; background:#fafafa;}
/* focus e interazioni */
.cltable tr:focus-within, .cltable tr:hover{background:#fcfcff;}
/* responsive wrapper (se messa in un container scrollabile) */
.cltable[data-scroll="x"]{display:block; overflow:auto;}

/* ====== Tema: zebra ====== */
.cltable--zebra tbody tr:nth-child(odd){background:#fcfcfc;}
.cltable--zebra thead th{background:#f9fafb; border-bottom:1px solid #e5e7eb;}

/* ====== Tema: minimal ====== */
.cltable--minimal{border:1px solid #eee;}
.cltable--minimal thead th{background:#ffffff; border-bottom:1px solid #eee;}
.cltable--minimal tbody tr:hover{background:#fafafa;}

/* helpers */
.cltable .text-right{text-align:right;}
.cltable .text-center{text-align:center;}
.cltable .badge{display:inline-block; padding:.2rem .5rem; border-radius:999px; background:#eef2ff;}
.cltable .badge-warn{background:#fff7ed;}

/* ====== Tema: boxed (griglia completa) ====== */
.cltable--boxed{
  border:1px solid #d1d5db; /* bordo esterno più visibile */
  border-radius:8px;
  border-collapse:separate; /* per gestire i bordi cella */
  border-spacing:0;
  overflow:hidden;
}
.cltable--boxed thead th{
  background:#f3f4f6;
  border-bottom:1px solid #d1d5db;
}
.cltable--boxed th, .cltable--boxed td{
  border-right:1px solid #e5e7eb;
  border-bottom:1px solid #e5e7eb;
  white-space:nowrap;
}
.cltable--boxed tbody tr:hover{ background:#fafafa; }

/* rimuovi il bordo destro sull’ultima cella di ogni riga per estetica */
.cltable--boxed tr > *:last-child{ border-right:none; }

/* arrotondamento visivo agli angoli della tabella */
.cltable--boxed thead tr:first-child th:first-child{ border-top-left-radius:8px; }
.cltable--boxed thead tr:first-child th:last-child{ border-top-right-radius:8px; }
.cltable--boxed tfoot tr:last-child td:first-child{ border-bottom-left-radius:8px; }
.cltable--boxed tfoot tr:last-child td:last-child{ border-bottom-right-radius:8px; }
</style>
CSS;

        return $css;
    }
}
