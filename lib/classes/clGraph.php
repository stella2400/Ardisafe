<?php
/**
 * CLGraph.php — v0.9.2 (LITE)
 *
 * Generatore di grafici SVG da array di dati, senza dipendenze JS.
 *
 * Tipi supportati (LITE):
 *  - line    (multi-serie)
 *  - area    (multi-serie, baseline auto 0 o min)
 *  - bar     (barre verticali; multi-serie raggruppate; richiede categories())
 *  - barh    (barre orizzontali; richiede categories())
 *  - scatter (x/y numerico)
 *  - pie     (torta; usa ->pieData([...]))
 *  - donut   (anello; usa ->pieData([...]))
 *
 * Funzioni principali:
 *  start(width, height, attrs) → definisce canvas e wrapper
 *  type('line'|'area'|'bar'|'barh'|'scatter'|'pie'|'donut')
 *  categories([...]) → categorie X per bar/barh/line/area discreti
 *  addSeries(name, data, opts) → aggiunge una serie (line/area/bar/scatter)
 *  pieData([label=>value, ...]) → dati per pie/donut
 *  legend(show, position) → mostra legenda (top|bottom|right)
 *  grid(x, y) → griglie
 *  yRange(min, max) / xRange(min, max) → override dominio
 *  margins(t, r, b, l) → margini
 *  palette([...]) → colori personalizzati
 *  caption(text) → figcaption opzionale
 *  render() → restituisce HTML ( <figure><svg>...</svg></figure> )
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLGraph
{
    // --- Config ---
    protected int $width = 640;
    protected int $height = 360;
    /** @var array{top:int,right:int,bottom:int,left:int} */
    protected array $margins = ['top'=>24,'right'=>16,'bottom'=>40,'left'=>48];

    protected string $type = 'line';
    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    /** @var array<string,mixed> */
    protected array $wrapperAttrs = ['class' => 'clgraph'];
    protected ?string $title = null; // (non disegnato, utile per future estensioni)
    protected ?string $caption = null;

    /** Palette colori di default */
    protected array $palette = [
        '#111827','#2563eb','#16a34a','#f59e0b','#ef4444',
        '#7c3aed','#0ea5e9','#22c55e','#e11d48','#a16207'
    ];

    /** Serie: ognuna {name, data, color, opts} */
    /** @var array<int, array{name:string, data:array, color:?string, opts:array}> */
    protected array $series = [];

    /** Dati torta: [label=>value] */
    protected ?array $pie = null;

    /** Categorie per X discreta */
    protected array $categories = [];

    /** Range override */
    protected ?array $xRange = null; // [min,max]
    protected ?array $yRange = null; // [min,max]

    /** Opzioni cartesian */
    protected bool $gridX = false;
    protected bool $gridY = true;
    protected bool $showAxes = true;
    protected int $yTicks = 5;
    protected ?int $xTicks = null; // se null: auto

    /** Legenda */
    protected bool $showLegend = true;
    protected string $legendPos = 'bottom'; // top | bottom | right

    // ===== API =====
    public function start(int $width = 640, int $height = 360, array $attrs = []): self
    {
        $this->width = max(160, $width);
        $this->height = max(120, $height);
        $this->wrapperAttrs = array_replace($this->wrapperAttrs, $attrs);
        $this->ensureClass('clgraph');
        return $this;
    }

    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    public function type(string $type): self
    {
        $allowed = ['line','area','bar','barh','scatter','pie','donut'];
        if (in_array($type, $allowed, true)) $this->type = $type;
        return $this;
    }

    public function size(int $w, int $h): self { $this->width = $w; $this->height = $h; return $this; }

    public function margins(int $top, int $right, int $bottom, int $left): self
    { $this->margins = compact('top','right','bottom','left'); return $this; }

    public function palette(array $colors): self { $this->palette = $colors; return $this; }

    public function caption(string $text): self { $this->caption = $text; return $this; }

    public function categories(array $labels): self { $this->categories = array_values($labels); return $this; }

    public function xRange(float $min, float $max): self { $this->xRange = [$min,$max]; return $this; }
    public function yRange(float $min, float $max): self { $this->yRange = [$min,$max]; return $this; }

    public function grid(bool $x = false, bool $y = true): self { $this->gridX = $x; $this->gridY = $y; return $this; }
    public function axes(bool $on = true): self { $this->showAxes = $on; return $this; }
    public function ticks(?int $xTicks = null, int $yTicks = 5): self { $this->xTicks = $xTicks; $this->yTicks = $yTicks; return $this; }

    public function legend(bool $show = true, string $position = 'bottom'): self
    {
        $allowed = ['top','bottom','right'];
        $this->showLegend = $show; $this->legendPos = in_array($position, $allowed, true) ? $position : 'bottom';
        return $this;
    }

    /**
     * Aggiunge una serie.
     * Per cartesian (line/area/scatter):
     *  - data può essere: [ [x,y], [x,y], ... ] oppure [['x'=>..,'y'=>..], ...]
     *    Oppure con categories(): [y1, y2, ...] (indice = categoria)
     * Per bar/barh: richiede categories(); data come [y1,y2,...]
     * opts: ['color'=>'#hex','strokeWidth'=>2,'opacity'=>1.0,'shape'=>'circle']
     */
    public function addSeries(string $name, array $data, array $opts = []): self
    {
        $this->series[] = [
            'name'  => $name,
            'data'  => $data,
            'color' => $opts['color'] ?? null,
            'opts'  => $opts,
        ];
        return $this;
    }

    /** Dati per pie/donut: array label=>value */
    public function pieData(array $pairs): self { $this->pie = $pairs; return $this; }

    /** Render finale */
    public function render(): string
    {
        $out = [];
        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        $figAttrs = $this->attrsToString($this->wrapperAttrs);
        $svg = $this->renderSVG();
        $out[] = '<figure' . $figAttrs . '>' . $svg . ($this->caption ? '<figcaption>'.$this->esc($this->caption).'</figcaption>' : '') . '</figure>';
        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    // ====== SVG core ======
    protected function renderSVG(): string
    {
        $w = $this->width; $h = $this->height;
        // Legend space adjustment (simple heuristic)
        $m = $this->margins;
        if ($this->showLegend && in_array($this->legendPos, ['bottom','top'], true)) {
            $m[$this->legendPos] += 24; // spazio per legenda
        }
        $plotW = max(10, $w - $m['left'] - $m['right']);
        $plotH = max(10, $h - $m['top'] - $m['bottom']);

        $content = [];
        $content[] = '<svg class="clgraph__svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" xmlns="http://www.w3.org/2000/svg" role="img">';
        $content[] = '<rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="none"/>';

        // Plot group
        $content[] = '<g transform="translate('.$m['left'].','.$m['top'].')">';

        switch ($this->type) {
            case 'pie':
            case 'donut':
                $content[] = $this->drawPie($plotW, $plotH);
                break;
            case 'bar':
            case 'barh':
            case 'line':
            case 'area':
            case 'scatter':
            default:
                $content[] = $this->drawCartesian($plotW, $plotH);
                break;
        }

        $content[] = '</g>'; // end plot

        // Legend inside root SVG (positioned in margin area)
        if ($this->showLegend) {
            $content[] = $this->drawLegend($m, $w, $h);
        }

        $content[] = '</svg>';
        return implode("\n", $content);
    }

    // ====== Legend ======
    protected function drawLegend(array $m, int $w, int $h): string
    {
        if (($this->type === 'pie' || $this->type === 'donut') && $this->pie) {
            // legenda basata su fette
            $items = [];
            foreach ($this->pie as $label => $val) {
                static $i = 0; $color = $this->colorFor($i++);
                $items[] = ['name'=>(string)$label, 'color'=>$color];
            }
            return $this->legendGroup($items, $m, $w, $h);
        }
        if (empty($this->series)) return '';
        $items = [];
        foreach ($this->series as $i => $s) {
            $items[] = ['name'=>$s['name'], 'color'=>$s['color'] ?? $this->colorFor($i)];
        }
        return $this->legendGroup($items, $m, $w, $h);
    }

    protected function legendGroup(array $items, array $m, int $w, int $h): string
    {
        if (empty($items)) return '';
        $y = ($this->legendPos === 'top') ? ($m['top'] - 8) : ($h - $m['bottom'] + 18);
        $x = $m['left'];
        $gap = 12; $swatch = 10; $tx = $x; $parts = [];
        $parts[] = '<g class="clgraph__legend" transform="translate(0,0)">';
        foreach ($items as $it) {
            $label = $this->esc($it['name']);
            $color = $this->escAttr($it['color']);
            $parts[] = '<g transform="translate('.$tx.','.$y.')">';
            $parts[] = '<rect x="0" y="-8" width="'.$swatch.'" height="10" fill="'.$color.'" rx="2" />';
            $parts[] = '<text x="'.($swatch+6).'" y="0" class="clgraph__legend-label">'.$label.'</text>';
            $parts[] = '</g>';
            $tx += $swatch + 6 + max(40, 6 * strlen($it['name'])) + $gap; // stima larghezza
        }
        $parts[] = '</g>';
        return implode("\n", $parts);
    }

    // ====== Cartesian charts ======
    protected function drawCartesian(int $plotW, int $plotH): string
    {
        $parts = [];
        $cats = $this->categories;
        $isBar = ($this->type === 'bar' || $this->type === 'barh');
        if ($isBar && empty($cats)) {
            // Lite: richiediamo categories per i grafici a barre
            $cats = $this->categories = array_map(fn($i)=> (string)$i, range(1, max(1, $this->inferMaxLen())));
        }

        // normalizza dati
        $norm = $this->normalizeSeries($cats);
        $xIsNumeric = $norm['xType'] === 'numeric';

        // scale domain
        $xMin = $norm['xMin']; $xMax = $norm['xMax'];
        if ($this->xRange) { $xMin = $this->xRange[0]; $xMax = $this->xRange[1]; }
        $yMin = $norm['yMin']; $yMax = $norm['yMax'];
        if ($this->yRange) { $yMin = $this->yRange[0]; $yMax = $this->yRange[1]; }
        // Miglioria: per bar/barh default baseline a 0 quando i dati sono >=0 o <=0
        if ($this->yRange === null && $isBar) {
            if ($yMin > 0) $yMin = 0.0; // tutte positive
            if ($yMax < 0) $yMax = 0.0; // tutte negative
        }
        [$yMinTick, $yMaxTick, $yStep] = $this->niceTicks($yMin, $yMax, max(2, $this->yTicks));

        // scale helpers
        $sx = function(float $x) use ($xMin, $xMax, $plotW): float {
            if ($xMax == $xMin) return 0.0; return ($x - $xMin) / ($xMax - $xMin) * $plotW;
        };
        $sy = function(float $y) use ($yMinTick, $yMaxTick, $plotH): float {
            if ($yMaxTick == $yMinTick) return $plotH; return $plotH - (($y - $yMinTick) / ($yMaxTick - $yMinTick) * $plotH);
        };

        // grid + axes
        if ($this->showAxes) {
            // Y grid & ticks
            for ($yy = $yMinTick; $yy <= $yMaxTick + 1e-9; $yy += $yStep) {
                $Y = $sy($yy);
                if ($this->gridY) $parts[] = '<line class="clgraph__grid" x1="0" y1="'.$Y.'" x2="'.$plotW.'" y2="'.$Y.'" />';
                $parts[] = '<text class="clgraph__tick clgraph__tick--y" x="-6" y="'.$Y.'">'. $this->esc($this->fmtTick($yy)) .'</text>';
            }
            // X axis ticks
            if ($xIsNumeric) {
                $xTicks = $this->xTicks ?? 6;
                [$xMinTick,$xMaxTick,$xStep] = $this->niceTicks($xMin, $xMax, max(2,$xTicks));
                for ($xx = $xMinTick; $xx <= $xMaxTick + 1e-9; $xx += $xStep) {
                    $X = $sx($xx);
                    if ($this->gridX) $parts[] = '<line class="clgraph__grid" x1="'.$X.'" y1="0" x2="'.$X.'" y2="'.$plotH.'" />';
                    $parts[] = '<text class="clgraph__tick clgraph__tick--x" x="'.$X.'" y="'.($plotH+16).'">'. $this->esc($this->fmtTick($xx)) .'</text>';
                }
            } else {
                // categorie (tick centrati sulla banda → niente "sfalsamento" con barre)
                $n = max(1, count($cats));
                for ($i=0; $i<$n; $i++) {
                    $X = $this->catBandX($i, $n, $plotW, true); // centrato
                    if ($this->gridX) $parts[] = '<line class="clgraph__grid" x1="'.$X.'" y1="0" x2="'.$X.'" y2="'.$plotH.'" />';
                    $label = isset($cats[$i]) ? (string)$cats[$i] : (string)($i+1);
                    $parts[] = '<text class="clgraph__tick clgraph__tick--x" x="'.$X.'" y="'.($plotH+16).'">'. $this->esc($label) .'</text>';
                }
            }
            // axis lines
            $parts[] = '<line class="clgraph__axis" x1="0" y1="'.$plotH.'" x2="'.$plotW.'" y2="'.$plotH.'" />';
            $parts[] = '<line class="clgraph__axis" x1="0" y1="0" x2="0" y2="'.$plotH.'" />';
        }

        // draw data
        if ($this->type === 'bar') {
            $parts[] = $this->drawBars($norm, $plotW, $plotH, $sx, $sy, false);
        } elseif ($this->type === 'barh') {
            $parts[] = $this->drawBars($norm, $plotW, $plotH, $sx, $sy, true);
        } elseif ($this->type === 'scatter') {
            foreach ($norm['series'] as $si => $S) {
                $color = $S['color'] ?? $this->colorFor($si);
                foreach ($S['points'] as [$x,$y]) {
                    $parts[] = '<circle cx="'.$sx($x).'" cy="'.$sy($y).'" r="3" fill="'.$this->escAttr($color).'" />';
                }
            }
        } else { // line / area
            foreach ($norm['series'] as $si => $S) {
                $color = $S['color'] ?? $this->colorFor($si);
                $path = [];
                foreach ($S['points'] as $idx => [$x,$y]) {
                    $X = $sx($x); $Y = $sy($y);
                    $path[] = ($idx===0 ? 'M' : 'L') . $X . ' ' . $Y;
                }
                if ($this->type === 'area') {
                    // baseline a 0 se nel dominio, altrimenti yMinTick
                    $baseline = ($yMinTick < 0 && $yMaxTick > 0) ? 0.0 : $yMinTick;
                    $X0 = $sx($S['points'][0][0]); $X1 = $sx($S['points'][count($S['points'])-1][0]);
                    $Y0 = $sy($baseline);
                    $areaPath = implode(' ', $path) . ' L ' . $X1 . ' ' . $Y0 . ' L ' . $X0 . ' ' . $Y0 . ' Z';
                    $parts[] = '<path d="'.$areaPath.'" fill="'.$this->escAttr($color).'" opacity="0.16" />';
                }
                $strokeW = (float)($S['opts']['strokeWidth'] ?? 2.0);
                $parts[] = '<path d="'.implode(' ',$path).'" fill="none" stroke="'.$this->escAttr($color).'" stroke-width="'.$strokeW.'" stroke-linecap="round" stroke-linejoin="round" />';
            }
        }

        return implode("\n", $parts);
    }

    protected function drawBars(array $norm, int $plotW, int $plotH, callable $sx, callable $sy, bool $horizontal): string
    {
        $parts = [];
        $nCats = max(1, count($this->categories));
        $nSeries = max(1, count($norm['series']));

        if ($horizontal) {
            // barh: categorie su Y, valore su X
            $bandH = $plotH / $nCats;
            $barH = max(2, $bandH * 0.7 / $nSeries);
            foreach ($norm['series'] as $si => $S) {
                $color = $S['color'] ?? $this->colorFor($si);
                for ($i=0; $i<$nCats; $i++) {
                    $yVal = $S['points'][$i][1] ?? 0.0;
                    $Yc = $i*$bandH + ($bandH - $barH*$nSeries)/2 + $si*$barH;
                    $x0 = min($sx(0), $sx($yVal));
                    $x1 = max($sx(0), $sx($yVal));
                    $parts[] = '<rect x="'.$x0.'" y="'.$Yc.'" width="'.max(1,$x1-$x0).'" height="'.$barH.'" fill="'.$this->escAttr($color).'" opacity="0.85" />';
                }
            }
        } else {
            // bar verticale: categorie su X, valore su Y
            $bandW = $plotW / $nCats;
            $barW = max(2, $bandW * 0.7 / $nSeries);
            foreach ($norm['series'] as $si => $S) {
                $color = $S['color'] ?? $this->colorFor($si);
                for ($i=0; $i<$nCats; $i++) {
                    $yVal = $S['points'][$i][1] ?? 0.0;
                    $Xc = $i*$bandW + ($bandW - $barW*$nSeries)/2 + $si*$barW;
                    $Y0 = $sy(0); $Y1 = $sy($yVal);
                    $y = min($Y0, $Y1); $h = abs($Y1 - $Y0);
                    $parts[] = '<rect x="'.$Xc.'" y="'.$y.'" width="'.$barW.'" height="'.max(1,$h).'" fill="'.$this->escAttr($color).'" opacity="0.85" />';
                }
            }
        }
        return implode("\n", $parts);
    }

    // ====== Pie/Donut ======
    protected function drawPie(int $plotW, int $plotH): string
    {
        $parts = [];
        $pairs = $this->pie ?? [];
        $sum = array_sum($pairs ?: [1]);
        if ($sum <= 0) $sum = 1;
        $cx = $plotW/2; $cy = $plotH/2;
        $R = max(20.0, min($plotW, $plotH)/2 - 2);
        $rInner = ($this->type === 'donut') ? $R * 0.55 : 0.0;
        $startAngle = -M_PI/2; // parte dall'alto
        $acc = $startAngle; $idx = 0;
        foreach ($pairs as $label => $val) {
            $angle = ($val / $sum) * 2 * M_PI;
            $color = $this->colorFor($idx++);
            $parts[] = $this->donutSlice($cx, $cy, $rInner, $R, $acc, $acc + $angle, $color);
            $acc += $angle;
        }
        return implode("\n", $parts);
    }

    protected function donutSlice(float $cx, float $cy, float $r0, float $r1, float $a0, float $a1, string $color): string
    {
        // grande arco? flag
        $large = (($a1 - $a0) % (2*M_PI)) > M_PI ? 1 : 0;
        $x0 = $cx + $r1 * cos($a0); $y0 = $cy + $r1 * sin($a0);
        $x1 = $cx + $r1 * cos($a1); $y1 = $cy + $r1 * sin($a1);
        if ($r0 <= 0.0) {
            // torta piena
            $d = 'M '.$cx.' '.$cy.' L '.$x0.' '.$y0.' A '.$r1.' '.$r1.' 0 '.$large.' 1 '.$x1.' '.$y1.' Z';
            return '<path d="'.$d.'" fill="'.$this->escAttr($color).'" opacity="0.9" />';
        }
        $x0i = $cx + $r0 * cos($a1); $y0i = $cy + $r0 * sin($a1);
        $x1i = $cx + $r0 * cos($a0); $y1i = $cy + $r0 * sin($a0);
        $d = 'M '.$x0.' '.$y0.
             ' A '.$r1.' '.$r1.' 0 '.$large.' 1 '.$x1.' '.$y1.
             ' L '.$x0i.' '.$y0i.
             ' A '.$r0.' '.$r0.' 0 '.$large.' 0 '.$x1i.' '.$y1i.' Z';
        return '<path d="'.$d.'" fill="'.$this->escAttr($color).'" opacity="0.9" />';
    }

    // ====== Normalizzazione dati ======
    protected function normalizeSeries(array $cats): array
    {
        $seriesOut = [];
        $allX = []; $allY = [];
        $xType = 'numeric';

        $hasCats = !empty($cats);
        foreach ($this->series as $S) {
            $points = [];
            $data = $S['data'];
            if ($hasCats && $this->type !== 'scatter') {
                // ci aspettiamo un array di y per ogni categoria
                $n = count($cats);
                for ($i=0; $i<$n; $i++) {
                    $y = isset($data[$i]) ? (float)$data[$i] : 0.0;
                    $points[] = [$i, $y];
                    $allX[] = $i; $allY[] = $y;
                }
                $xType = 'categorical';
            } else {
                foreach ($data as $row) {
                    if (is_array($row)) {
                        if (array_is_list($row)) { $x=(float)($row[0]??0); $y=(float)($row[1]??0); }
                        else { $x=(float)($row['x']??0); $y=(float)($row['y']??0); }
                        $points[] = [$x,$y]; $allX[]=$x; $allY[]=$y;
                    }
                }
            }
            usort($points, fn($a,$b)=> $a[0]<=>$b[0]);
            $seriesOut[] = ['name'=>$S['name'], 'points'=>$points, 'color'=>$S['color'] ?? null, 'opts'=>$S['opts'] ?? []];
        }

        $xMin = empty($allX) ? 0.0 : (float)min($allX);
        $xMax = empty($allX) ? 1.0 : (float)max($allX);
        if ($xMin === $xMax) { $xMin -= 1; $xMax += 1; }
        $yMin = empty($allY) ? 0.0 : (float)min($allY);
        $yMax = empty($allY) ? 1.0 : (float)max($allY);
        if ($yMin === $yMax) { $yMin -= 1; $yMax += 1; }

        return [
            'series' => $seriesOut,
            'xType'  => $xType,
            'xMin'   => $xMin,
            'xMax'   => $xMax,
            'yMin'   => $yMin,
            'yMax'   => $yMax,
        ];
    }

    protected function inferMaxLen(): int
    {
        $max = 0;
        foreach ($this->series as $S) { $max = max($max, is_array($S['data']) ? count($S['data']) : 0); }
        return max(1, $max);
    }

    // ====== Utils ======
    protected function niceTicks(float $min, float $max, int $ticks): array
    {
        $rng = $max - $min; if ($rng <= 0) $rng = 1.0;
        $rawStep = $rng / max(1,$ticks);
        $mag = pow(10, floor(log10($rawStep)));
        $norm = $rawStep / $mag;
        if ($norm < 1.5)      $nice = 1;
        elseif ($norm < 3)    $nice = 2;
        elseif ($norm < 7)    $nice = 5;
        else                   $nice = 10;
        $step = $nice * $mag;
        $minTick = floor($min / $step) * $step;
        $maxTick = ceil($max / $step) * $step;
        return [$minTick, $maxTick, $step];
    }

    protected function fmtTick(float $v): string
    {
        // formato breve
        $abs = abs($v);
        if ($abs >= 1e6) return sprintf('%.1fM', $v/1e6);
        if ($abs >= 1e3) return sprintf('%.1fk', $v/1e3);
        if ($abs >= 1)   return rtrim(rtrim(sprintf('%.2f',$v), '0'), '.');
        return rtrim(rtrim(sprintf('%.3f',$v), '0'), '.');
    }

    protected function catBandX(int $i, int $n, int $plotW, bool $center = true): float
    { $band = $plotW / max(1,$n); return $i*$band + ($center ? $band/2 : $band); }

    protected function colorFor(int $idx): string
    { return $this->palette[$idx % max(1,count($this->palette))] ?? '#111827'; }

    protected function ensureClass(string $cls): void
    {
        $curr = (string)($this->wrapperAttrs['class'] ?? '');
        if (!preg_match('/(^|\s)'.preg_quote($cls,'/').'(\s|$)/', $curr)) {
            $this->wrapperAttrs['class'] = trim($curr.' '.$cls);
        }
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
        /* ====== CLGraph base ====== */
        .clgraph{display:block; max-width:100%;}
        .clgraph__svg{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; font-size:12px; color:#111827;}
        .clgraph figcaption{margin-top:6px; color:#6b7280; font-size:12px;}
        /* axes & grid */
        .clgraph__axis{stroke:#111827; stroke-width:1; shape-rendering:crispEdges;}
        .clgraph__grid{stroke:#e5e7eb; stroke-width:1; shape-rendering:crispEdges;}
        .clgraph__tick{fill:#374151; dominant-baseline:middle; text-anchor:end;}
        .clgraph__tick--x{text-anchor:middle;}
        /* legend */
        .clgraph__legend-label{fill:#374151; dominant-baseline:middle;}
        </style>
        CSS;
        return $css;
    }
}
