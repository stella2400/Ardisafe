<?php
/**
 * CLCard.php — v1.0
 *
 * Builder per card/fluid panel senza HTML manuale.
 * - CSS integrato (iniettato una sola volta) con varianti: elevated | outlined | soft
 * - Sezioni: header (titolo/sottotitolo/azioni), media (img o slot raw), body (uno o più blocchi),
 *   list(), footer, badge/ribbon.
 * - API fluente: start()->header()->media()->body()->list()->footer()->render()
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLCard
{
    /** @var array<string,mixed> */
    protected array $attrs = [];
    protected bool $started = false;

    protected string $theme = 'elevated'; // elevated | outlined | soft
    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    // sezioni
    protected ?array $header = null; // ['title'=>..., 'subtitle'=>..., 'actionsRaw'=>..., 'attrs'=>[]]
    protected ?array $media = null;  // ['mode'=>'img'|'raw', 'src'=>..., 'alt'=>..., 'opts'=>[]]
    /** @var array<int, array> */
    protected array $bodyBlocks = []; // ognuno: ['type'=>'html'|'list','content'=>..., 'raw'=>bool, 'attrs'=>[]]
    protected ?array $footer = null; // ['content'=>..., 'raw'=>bool, 'attrs'=>[]]
    protected ?array $badge  = null; // ['text'=>..., 'attrs'=>[]]

    /** ====== Config base ====== */
    public function start(array $attrs = []): self
    {
        $this->started = true;
        $this->attrs = array_replace(['class' => 'clcard'], $attrs);
        $this->addClass("clcard--{$this->theme}");
        return $this;
    }

    public function theme(string $variant): self
    {
        $allowed = ['elevated','outlined','soft'];
        if (!in_array($variant, $allowed, true)) return $this;
        // sostituisci modifier
        if (!empty($this->attrs['class'])) {
            $this->attrs['class'] = preg_replace('/\bclcard--(elevated|outlined|soft)\b/', '', (string)$this->attrs['class']);
        }
        $this->theme = $variant;
        $this->addClass("clcard--{$variant}");
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

    /** ====== Sezioni ====== */

    public function header(string $title = '', ?string $subtitle = null, ?string $actionsRaw = null, array $attrs = []): self
    {
        $this->ensureStarted();
        $this->header = compact('title','subtitle','actionsRaw','attrs');
        return $this;
    }

    /** media immagine */
    public function media(string $src, string $alt = '', array $opts = []): self
    {
        $this->ensureStarted();
        $opts = array_replace([
            'ratio' => '16/9',  // usa CSS aspect-ratio
            'cover' => true,    // object-fit: cover
            'attrs' => [],
        ], $opts);
        $this->media = ['mode' => 'img', 'src' => $src, 'alt' => $alt, 'opts' => $opts];
        return $this;
    }

    /** media raw (HTML personalizzato: iframe, video, ecc.) */
    public function mediaRaw(string $html, array $opts = []): self
    {
        $this->ensureStarted();
        $opts = array_replace(['ratio' => null, 'attrs' => []], $opts);
        $this->media = ['mode' => 'raw', 'html' => $html, 'opts' => $opts];
        return $this;
    }

    /** blocco body (puoi chiamarlo più volte) */
    public function body(string $content, bool $raw = false, array $attrs = []): self
    {
        $this->ensureStarted();
        $this->bodyBlocks[] = ['type' => 'html', 'content' => $content, 'raw' => $raw, 'attrs' => $attrs];
        return $this;
    }

    /** lista semplice nel body */
    public function list(array $items, array $attrs = []): self
    {
        $this->ensureStarted();
        $this->bodyBlocks[] = ['type' => 'list', 'items' => $items, 'attrs' => $attrs];
        return $this;
    }

    public function badge(string $text, array $attrs = []): self
    {
        $this->badge = ['text' => $text, 'attrs' => $attrs];
        return $this;
    }

    public function footer(string $content, bool $raw = false, array $attrs = []): self
    {
        $this->ensureStarted();
        $this->footer = compact('content','raw','attrs');
        return $this;
    }

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

        if ($this->badge !== null) {
            $out[] = '  <div class="clcard__badge"' . $this->attrsToString($this->badge['attrs']) . '>' . $this->esc($this->badge['text']) . '</div>';
        }

        if ($this->media !== null) {
            $out[] = $this->renderMedia($this->media);
        }

        if ($this->header !== null && ($this->header['title'] !== '' || !empty($this->header['subtitle']) || !empty($this->header['actionsRaw']))) {
            $h = $this->header;
            $out[] = '  <div class="clcard__header"' . $this->attrsToString($h['attrs'] ?? []) . '>';
            $out[] = '    <div class="clcard__titles">';
            if ($h['title'] !== '')    $out[] = '      <div class="clcard__title">' . $this->esc($h['title']) . '</div>';
            if (!empty($h['subtitle'])) $out[] = '      <div class="clcard__subtitle">' . $this->esc((string)$h['subtitle']) . '</div>';
            $out[] = '    </div>';
            if (!empty($h['actionsRaw'])) $out[] = '    <div class="clcard__actions">' . $h['actionsRaw'] . '</div>';
            $out[] = '  </div>';
        }

        if (!empty($this->bodyBlocks)) {
            $out[] = '  <div class="clcard__body">';
            foreach ($this->bodyBlocks as $block) {
                if ($block['type'] === 'list') {
                    $out[] = '    <ul class="clcard__list"' . $this->attrsToString($block['attrs']) . '>';
                    foreach ($block['items'] as $li) {
                        $out[] = '      <li>' . $this->esc((string)$li) . '</li>';
                    }
                    $out[] = '    </ul>';
                } else {
                    $content = $block['raw'] ? (string)$block['content'] : $this->esc((string)$block['content']);
                    $out[] = '    <div class="clcard__block"' . $this->attrsToString($block['attrs']) . '>' . $content . '</div>';
                }
            }
            $out[] = '  </div>';
        }

        if ($this->footer !== null) {
            $f = $this->footer;
            $c = $f['raw'] ? (string)$f['content'] : $this->esc((string)$f['content']);
            $out[] = '  <div class="clcard__footer"' . $this->attrsToString($f['attrs'] ?? []) . '>' . $c . '</div>';
        }

        $out[] = '</div>';
        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    /** ====== Helpers ====== */

    protected function renderMedia(array $m): string
    {
        $ratio = $m['opts']['ratio'] ?? null;
        $ratioStyle = $ratio ? ' style="aspect-ratio:' . $this->escAttr((string)$ratio) . ';"' : '';
        $wrap = '<div class="clcard__media"' . $ratioStyle . $this->attrsToString($m['opts']['attrs']) . '>';

        if ($m['mode'] === 'img') {
            $imgAttrs = [
                'src' => $m['src'],
                'alt' => $m['alt'],
                'class' => !empty($m['opts']['cover']) ? 'is-cover' : null
            ];
            return $wrap . '<img' . $this->attrsToString($imgAttrs) . '></div>';
        }

        // raw slot
        return $wrap . (string)$m['html'] . '</div>';
    }

    protected function ensureStarted(): void
    {
        if (!$this->started) throw new \LogicException('Chiama start() prima di aggiungere sezioni della card.');
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
        /* ====== CLCard base ====== */
        .clcard{--pad:16px; --pad-body:14px; --radius:12px; --shadow:0 8px 20px rgba(0,0,0,.06);
        --b:#e5e7eb; --muted:#6b7280; --title:#111827; --bg:#fff;
        background:var(--bg); border-radius:var(--radius); border:1px solid transparent; overflow:hidden; display:block; color:#111827;
        }
        .clcard--elevated{box-shadow:var(--shadow); border-color:#0000;}
        .clcard--outlined{border-color:var(--b);}
        .clcard--soft{background:#f9fafb; border:1px solid #eef2f7;}
        .clcard__badge{position:absolute; transform:translate(10px,10px); background:#111827; color:#fff; font-size:12px; padding:4px 8px; border-radius:999px; display:inline-block;}
        .clcard__header{display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:var(--pad); border-bottom:1px solid #f1f5f9;}
        .clcard__titles{min-width:0;}
        .clcard__title{font-weight:700; color:var(--title);}
        .clcard__subtitle{color:var(--muted); font-size:13px; margin-top:2px;}
        .clcard__actions{display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
        .clcard__media{width:100%; background:#f3f4f6; display:block; overflow:hidden;}
        .clcard__media > img{width:100%; height:100%; display:block;}
        .clcard__media > img.is-cover{object-fit:cover;}
        .clcard__body{padding:var(--pad-body) var(--pad);}
        .clcard__block + .clcard__block{margin-top:8px;}
        .clcard__list{padding-left:18px; color:#111827;}
        .clcard__list li{margin:6px 0;}
        .clcard__footer{padding:var(--pad); border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:flex-end; gap:8px;}
        /* posizionamento badge "assoluto" senza rompere layout */
        .clcard{position:relative;}
        </style>
        CSS;
        return $css;
    }
}
