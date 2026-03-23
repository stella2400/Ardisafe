<?php
/**
 * CLWidget.php — v1.0
 *
 * Widget sintetico per metriche/statistiche con mini sparkline SVG.
 * - CSS integrato (una sola volta); varianti: neutral | success | warning | danger
 * - API: start()->icon()->metric()->progress()->sparkline($points)->footer()->render()
 * - Escaping sicuro; HTML raw per icone e footer
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLWidget
{
    /** @var array<string,mixed> */
    protected array $attrs = [];
    protected bool $started = false;

    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    protected string $variant = 'neutral'; // neutral | success | warning | danger

    // contenuto
    protected ?string $iconRaw = null;
    protected ?string $label = null;
    protected ?string $value = null;
    protected ?string $delta = null;      // es. +12% / -3
    protected string $deltaType = 'neutral'; // pos | neg | neutral

    protected ?int $progress = null;      // 0..100
    protected ?string $progressLabel = null;

    /** @var array<int,float|int> */
    protected array $spark = [];           // punti sparkline
    protected array $sparkOpts = ['width'=>160, 'height'=>40, 'stroke'=>1.8];

    protected ?string $footerRaw = null;

    /** ====== Config ====== */
    public function start(array $attrs = []): self
    {
        $this->started = true;
        $this->attrs = array_replace(['class' => 'clwidget'], $attrs);
        $this->addClass("clwidget--{$this->variant}");
        return $this;
    }

    public function variant(string $v): self
    {
        $allowed = ['neutral','success','warning','danger'];
        if (!in_array($v, $allowed, true)) return $this;
        if (!empty($this->attrs['class'])) {
            $this->attrs['class'] = preg_replace('/\bclwidget--(neutral|success|warning|danger)\b/', '', (string)$this->attrs['class']);
        }
        $this->variant = $v;
        $this->addClass("clwidget--{$v}");
        return $this;
    }

    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    public function addClass(string ...$classes): self
    {
        $existing = trim((string)($this->attrs['class'] ?? ''));
        $merged = trim($existing . ' ' . implode(' ', $classes));
        if ($merged !== '') $this->attrs['class'] = preg_replace('/\s+/', ' ', $merged);
        return $this;
    }

    public function setAttr(string $name, string|int|float|bool $value): self { $this->attrs[$name] = $value; return $this; }
    public function data(string $name, string|int|float|bool $value): self { $this->attrs['data-'.$name] = $value; return $this; }

    /** ====== Contenuto ====== */

    /** icona raw (SVG/HTML/emoji) */
    public function icon(string $htmlRaw): self { $this->iconRaw = $htmlRaw; return $this; }

    /** metrica principale */
    public function metric(string $value, string $label, ?string $delta = null, string $deltaType = 'neutral'): self
    {
        $this->value = $value;
        $this->label = $label;
        $this->delta = $delta;
        $this->deltaType = in_array($deltaType, ['pos','neg','neutral'], true) ? $deltaType : 'neutral';
        return $this;
    }

    public function progress(int $percent, ?string $label = null): self
    {
        $this->progress = max(0, min(100, $percent));
        $this->progressLabel = $label;
        return $this;
    }

    /**
     * Imposta i punti dello sparkline.
     * @param array<int, float|int> $points
     * @param array<string,int|float> $opts es. ['width'=>200,'height'=>40,'stroke'=>2.5]
     */
    public function sparkline(array $points, array $opts = []): self
    {
        $this->spark = array_values($points);
        $this->sparkOpts = array_replace($this->sparkOpts, $opts);
        return $this;
    }

    /** footer raw (link, azioni, testo formattato) */
    public function footerRaw(string $html): self { $this->footerRaw = $html; return $this; }

    /** ====== Render ====== */

    public function render(): string
    {
        $this->ensureStarted();
        $out = [];

        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        $out[] = '<div' . $this->attrsToString($this->attrs) . '>';

        if ($this->iconRaw !== null) {
            $out[] = '  <div class="clwidget__icon" aria-hidden="true">' . $this->iconRaw . '</div>';
        }

        $out[] = '  <div class="clwidget__main">';
        if ($this->label !== null) {
            $out[] = '    <div class="clwidget__label">' . $this->esc($this->label) . '</div>';
        }
        if ($this->value !== null) {
            $out[] = '    <div class="clwidget__value">' . $this->esc($this->value) . '</div>';
        }
        if ($this->delta !== null) {
            $out[] = '    <div class="clwidget__delta clwidget__delta--' . $this->escAttr($this->deltaType) . '">' . $this->esc($this->delta) . '</div>';
        }
        $out[] = '  </div>';

        if (!empty($this->spark)) {
            $out[] = '  <div class="clwidget__spark">' . $this->renderSparkline() . '</div>';
        }

        if ($this->progress !== null) {
            $out[] = '  <div class="clwidget__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $this->progress . '">';
            $out[] = '    <div class="clwidget__bar" style="width:' . $this->progress . '%;"></div>';
            if ($this->progressLabel !== null) {
                $out[] = '    <div class="clwidget__ptext">' . $this->esc($this->progressLabel) . '</div>';
            }
            $out[] = '  </div>';
        }

        if ($this->footerRaw !== null) {
            $out[] = '  <div class="clwidget__footer">' . $this->footerRaw . '</div>';
        }

        $out[] = '</div>';
        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    /** ====== Helpers ====== */

    protected function renderSparkline(): string
    {
        $w = (float)($this->sparkOpts['width'] ?? 160);
        $h = (float)($this->sparkOpts['height'] ?? 40);
        $stroke = (float)($this->sparkOpts['stroke'] ?? 1.8);
        $pad = 2.0;

        $pts = $this->spark;
        $n = count($pts);
        if ($n === 0) return '';
        $min = (float)min($pts);
        $max = (float)max($pts);
        $span = max(0.000001, $max - $min);

        $stepX = $n > 1 ? ($w - 2*$pad) / ($n - 1) : 0;
        $coords = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $pad + $i * $stepX;
            // inverti Y per avere base in basso
            $y = $pad + ($h - 2*$pad) * (1 - (($pts[$i] - $min) / $span));
            $coords[] = $x . ',' . $y;
        }

        $polyline = implode(' ', $coords);
        $last = end($coords);

        // area sotto la linea per un minimo “riempimento”
        $areaPoints = $polyline . ' ' . ($pad + ($n-1)*$stepX) . ',' . ($h - $pad) . ' ' . $pad . ',' . ($h - $pad);

        $svg = [];
        $svg[] = '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
        $svg[] = '  <polyline points="' . $areaPoints . '" fill="currentColor" opacity=".08"/>';
        $svg[] = '  <polyline points="' . $polyline . '" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round"/>';
        if ($last) {
            [$lx, $ly] = array_map('floatval', explode(',', $last));
            $svg[] = '  <circle cx="' . $lx . '" cy="' . $ly . '" r="2.5" fill="currentColor"/>';
        }
        $svg[] = '</svg>';
        return implode('', $svg);
    }

    protected function ensureStarted(): void
    {
        if (!$this->started) throw new \LogicException('Chiama start() prima di configurare il widget.');
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
        $css = <<<CSS
        <style>
        /* ====== CLWidget base ====== */
        .clwidget{--bg:#fff; --b:#e5e7eb; --muted:#6b7280; --fg:#111827; --accent:#111827;
        --radius:12px; --pad:14px; --shadow:0 8px 20px rgba(0,0,0,.06);
        display:grid; grid-template-columns:auto 1fr; gap:12px; align-items:center;
        background:var(--bg); border:1px solid var(--b); border-radius:var(--radius); padding:var(--pad);
        }
        .clwidget--neutral{--accent:#111827;}
        .clwidget--success{--accent:#065f46;}
        .clwidget--warning{--accent:#92400e;}
        .clwidget--danger{--accent:#b91c1c;}

        .clwidget__icon{width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent);}
        .clwidget__icon > *{max-width:24px; max-height:24px; display:block;}

        .clwidget__main{min-width:0;}
        .clwidget__label{color:var(--muted); font-size:12px;}
        .clwidget__value{font-weight:800; font-size:20px; color:var(--fg);}
        .clwidget__delta{font-size:12px; margin-top:2px;}
        .clwidget__delta--pos{color:#065f46;}
        .clwidget__delta--neg{color:#b91c1c;}
        .clwidget__delta--neutral{color:var(--muted);}

        .clwidget__spark{grid-column:1 / -1; color:var(--accent);}
        .clwidget__progress{grid-column:1 / -1; background:#f3f4f6; border-radius:999px; overflow:hidden; position:relative; height:8px; margin-top:2px;}
        .clwidget__bar{height:100%; background:var(--accent);}
        .clwidget__ptext{position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:11px; color:#0b0b0b;}
        .clwidget__footer{grid-column:1 / -1; display:flex; justify-content:flex-end; gap:8px; margin-top:6px;}
        </style>
        CSS;
        return $css;
    }
}
