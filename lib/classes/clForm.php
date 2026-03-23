<?php
/**
 * CLForm.php — v1.1
 *
 * Builder per generare form HTML senza scrivere tag a mano.
 * - CSS integrato (inserito una sola volta per request) con tema 'clean'
 * - API fluente: start()->text()->email()->password()->select()->checkbox()...
 * - Label, help text, error text, required/aria/data-*
 * - CSRF hidden, method spoof (_method)
 * - enctype auto per upload file
 * - Possibile disattivare CSS integrato con ->noDefaultCss()
 *
 * Requisiti: PHP 8.0+
 */

declare(strict_types=1);

class CLForm
{
    /** @var array<string, mixed> */
    protected array $formAttrs = [];
    protected bool $started = false;

    /** @var array<int, array> */
    protected array $fields = [];
    /** @var array<int, array> */
    protected array $buttons = [];

    protected bool $includeDefaultCss = true;
    protected static bool $stylePrinted = false;

    protected ?string $csrfName = null;
    protected ?string $csrfValue = null;

    protected bool $hasFileInput = false;

    /**
     * Avvia il form.
     * @param string $action
     * @param string $method GET|POST (per PUT/PATCH/DELETE usa ->methodSpoof())
     * @param array<string,mixed> $attrs  es. ['id'=>'frm', 'autocomplete'=>'off', 'novalidate'=>true]
     */
    public function start(string $action = '', string $method = 'POST', array $attrs = []): self
    {
        $this->started = true;

        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'POST';
        }

        $this->formAttrs = array_replace([
            'action' => $action,
            'method' => $method,
            'class'  => 'clform',
        ], $attrs);

        // garantiamo la classe base
        $this->addClass('clform--clean');

        return $this;
    }

    /** Dis/abilita CSS integrato */
    public function noDefaultCss(): self { $this->includeDefaultCss = false; return $this; }
    public function useDefaultCss(): self { $this->includeDefaultCss = true; return $this; }

    /** Imposta spoof del metodo: PUT/PATCH/DELETE (aggiunge hidden _method) */
    public function methodSpoof(string $verb): self
    {
        $verb = strtoupper($verb);
        if (in_array($verb, ['PUT','PATCH','DELETE'], true)) {
            $this->hidden('_method', $verb);
        }
        return $this;
    }

    /** Autocomplete on/off o string */
    public function autocomplete(string|bool $value): self
    {
        $this->formAttrs['autocomplete'] = is_bool($value) ? ($value ? 'on' : 'off') : $value;
        return $this;
    }

    /** Novalidate */
    public function noValidate(bool $on = true): self
    {
        if ($on) $this->formAttrs['novalidate'] = true;
        else unset($this->formAttrs['novalidate']);
        return $this;
    }

    /** Aggiunge classi custom al <form> */
    public function addClass(string ...$classes): self
    {
        $existing = trim((string)($this->formAttrs['class'] ?? ''));
        $merged = trim($existing . ' ' . implode(' ', $classes));
        if ($merged !== '') {
            $this->formAttrs['class'] = preg_replace('/\s+/', ' ', $merged);
        }
        return $this;
    }

    /** Set di attributi generici o data-* / aria-* */
    public function setAttr(string $name, string|int|float|bool $value): self { $this->formAttrs[$name] = $value; return $this; }
    public function data(string $name, string|int|float|bool $value): self { $this->formAttrs['data-' . $name] = $value; return $this; }
    public function aria(string $name, string|int|float|bool $value): self { $this->formAttrs['aria-' . $name] = $value; return $this; }

    /** CSRF hidden */
    public function csrf(string $name, string $value): self { $this->csrfName = $name; $this->csrfValue = $value; return $this; }

    /** --------- CAMPI --------- */

    /**
     * Campo base generico (privato). Usa ->text(), ->email(), ecc.
     * @param array<string,mixed> $opts
     */
    protected function addField(string $type, string $name, string $label = '', array $opts = []): self
    {
        $this->ensureStarted();

        $opts = array_replace([
            'id' => $name,
            'value' => null,
            'placeholder' => null,
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'help' => null,   // alias: 'hint'
            'error' => null,
            'attrs' => [],
            'labelAttrs' => [],
            'raw' => false, // se true: value/innerHTML senza escaping (usa con cautela)
        ], $opts);

        // alias 'hint' -> 'help'
        if (isset($opts['hint']) && !isset($opts['help'])) {
            $opts['help'] = $opts['hint'];
            unset($opts['hint']);
        }

        // per <input type="file">: supporta 'accept' passato come opzione
        if (isset($opts['accept'])) {
            $opts['attrs']['accept'] = $opts['accept'];
            unset($opts['accept']);
        }

        // per file input determiniamo l'enctype
        if ($type === 'file') $this->hasFileInput = true;

        $this->fields[] = [
            'kind'  => 'input',
            'type'  => $type,
            'name'  => $name,
            'label' => $label,
            'opts'  => $opts,
        ];

        return $this;
    }

    /** Text-like */
    public function text(string $name, string $label='', array $opts=[]): self { return $this->addField('text',$name,$label,$opts); }
    public function email(string $name, string $label='', array $opts=[]): self { return $this->addField('email',$name,$label,$opts); }
    public function password(string $name, string $label='', array $opts=[]): self { return $this->addField('password',$name,$label,$opts); }
    public function number(string $name, string $label='', array $opts=[]): self { return $this->addField('number',$name,$label,$opts); }
    public function date(string $name, string $label='', array $opts=[]): self { return $this->addField('date',$name,$label,$opts); }
    public function tel(string $name, string $label='', array $opts=[]): self { return $this->addField('tel',$name,$label,$opts); }
    public function url(string $name, string $label='', array $opts=[]): self { return $this->addField('url',$name,$label,$opts); }
    public function file(string $name, string $label='', array $opts=[]): self { return $this->addField('file',$name,$label,$opts); }
    public function hidden(string $name, string $value): self
    {
        $this->fields[] = ['kind'=>'hidden', 'name'=>$name, 'value'=>$value];
        return $this;
    }

    /**
     * Textarea
     * @param array<string,mixed> $opts es. ['rows'=>4,'placeholder'=>'...','value'=>'contenuto']
     */
    public function textarea(string $name, string $label='', array $opts=[]): self
    {
        $this->ensureStarted();
        $opts = array_replace([
            'id' => $name,
            'rows' => 4,
            'placeholder' => null,
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'help' => null,
            'error' => null,
            'attrs' => [],
            'labelAttrs' => [],
            'value' => '',
            'raw' => false,
        ], $opts);

        $this->fields[] = [
            'kind'  => 'textarea',
            'name'  => $name,
            'label' => $label,
            'opts'  => $opts,
        ];
        return $this;
    }

    /**
     * Select con opzioni o optgroup.
     * @param array<int|string, mixed> $options es. ['it'=>'Italia','fr'=>'Francia'] oppure
     *        [['label'=>'Europa','options'=>['it'=>'Italia','fr'=>'Francia']], ['label'=>'Asia','options'=>['jp'=>'Giappone']]]
     * @param array<string,mixed> $opts es. ['placeholder'=>'Seleziona...', 'multiple'=>true, 'value'=>'it'|['it','fr']]
     */
    public function select(string $name, string $label, array $options, array $opts=[]): self
    {
        $this->ensureStarted();
        $opts = array_replace([
            'id' => $name,
            'multiple' => false,
            'required' => false,
            'disabled' => false,
            'help' => null,
            'error' => null,
            'attrs' => [],
            'labelAttrs' => [],
            'placeholder' => null, // renderà un <option value="">...</option>
            'value' => null,       // string|array (per multiple)
            'raw' => false,
        ], $opts);

        $this->fields[] = [
            'kind'  => 'select',
            'name'  => $name,
            'label' => $label,
            'options' => $options,
            'opts'  => $opts,
        ];
        return $this;
    }

    /**
     * Checkbox singola
     * @param array<string,mixed> $opts es. ['checked'=>true,'help'=>'...','error'=>'...','attrs'=>[],'labelAfter'=>true]
     */
    public function checkbox(string $name, string $label, array $opts=[]): self
    {
        $this->ensureStarted();
        $opts = array_replace([
            'id' => $name,
            'checked' => false,
            'value' => '1',
            'disabled' => false,
            'required' => false,
            'help' => null,
            'error' => null,
            'attrs' => [],
            'labelAttrs' => [],
            'labelAfter' => true, // etichetta a destra
        ], $opts);

        $this->fields[] = [
            'kind'  => 'checkbox',
            'name'  => $name,
            'label' => $label,
            'opts'  => $opts,
        ];
        return $this;
    }

    /**
     * Radio group
     * @param array<string,array> $choices es. ['m'=>'Maschio','f'=>'Femmina'] oppure [['value'=>'m','label'=>'Maschio','attrs'=>[]], ...]
     */
    public function radioGroup(string $name, string $label, array $choices, array $opts=[]): self
    {
        $this->ensureStarted();
        $opts = array_replace([
            'id' => $name,
            'value' => null, // valore selezionato
            'required' => false,
            'disabled' => false,
            'help' => null,
            'error' => null,
            'attrs' => [], // wrapper attrs
            'labelAttrs' => [],
            'inline' => true,
        ], $opts);

        $this->fields[] = [
            'kind'    => 'radiogroup',
            'name'    => $name,
            'label'   => $label,
            'choices' => $choices,
            'opts'    => $opts,
        ];
        return $this;
    }

    /** Pulsanti */
    public function submit(string $text='Invia', array $attrs=[]): self
    {
        $this->buttons[] = ['type'=>'submit','text'=>$text,'attrs'=>$attrs];
        return $this;
    }
    public function button(string $text, array $attrs=[]): self
    {
        $this->buttons[] = ['type'=>'button','text'=>$text,'attrs'=>$attrs];
        return $this;
    }
    public function reset(string $text='Reset', array $attrs=[]): self
    {
        $this->buttons[] = ['type'=>'reset','text'=>$text,'attrs'=>$attrs];
        return $this;
    }

    /** Inserisce blocco HTML personalizzato (raw) – usalo con cautela */
    public function rawHtml(string $html): self
    {
        $this->fields[] = ['kind'=>'raw', 'html'=>$html];
        return $this;
    }

    /** Alias più corto di rawHtml() */
    public function raw(string $html): self { return $this->rawHtml($html); }

    /** Render del form */
    public function render(): string
    {
        $this->ensureStarted();

        $out = [];

        if ($this->includeDefaultCss && !self::$stylePrinted) {
            $out[] = $this->styleTag();
            self::$stylePrinted = true;
        }

        // enctype per file
        if ($this->hasFileInput) {
            $this->formAttrs['enctype'] = 'multipart/form-data';
        }

        $out[] = '<form' . $this->attrsToString($this->formAttrs) . '>';

        // CSRF
        if ($this->csrfName !== null) {
            $out[] = '  <input type="hidden" name="' . $this->escAttr($this->csrfName) . '" value="' . $this->escAttr((string)$this->csrfValue) . '">';
        }

        // hidden fields e altri
        foreach ($this->fields as $field) {
            $out[] = $this->renderField($field);
        }

        if (!empty($this->buttons)) {
            $out[] = '  <div class="clform__actions">';
            foreach ($this->buttons as $btn) {
                $out[] = '    <button type="' . $this->escAttr($btn['type']) . '"' . $this->attrsToString($btn['attrs']) . '>' . $this->esc((string)$btn['text']) . '</button>';
            }
            $out[] = '  </div>';
        }

        $out[] = '</form>';

        return implode("\n", $out);
    }

    public function __toString(): string { try { return $this->render(); } catch (\Throwable) { return ''; } }

    // ========= Helpers =========

    protected function ensureStarted(): void
    {
        if (!$this->started) {
            throw new \LogicException('Chiama start() prima di aggiungere campi.');
        }
    }

    /**
     * @param array<string,mixed> $attrs
     */
    protected function attrsToString(array $attrs): string
    {
        if (empty($attrs)) return '';
        $parts = [];
        foreach ($attrs as $k => $v) {
            if (is_bool($v)) { if ($v) $parts[] = $this->escAttr($k); continue; }
            if ($v === null) continue;
            $parts[] = $this->escAttr($k) . '="' . $this->escAttr((string)$v) . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /** @param array<string,mixed> $field */
    protected function renderField(array $field): string
    {
        switch ($field['kind']) {
            case 'hidden':
                return '  <input type="hidden" name="' . $this->escAttr($field['name']) . '" value="' . $this->escAttr((string)$field['value']) . '">';

            case 'raw':
                return '  ' . (string)$field['html'];

            case 'textarea':
                return $this->renderTextarea($field);

            case 'select':
                return $this->renderSelect($field);

            case 'checkbox':
                return $this->renderCheckbox($field);

            case 'radiogroup':
                return $this->renderRadioGroup($field);

            case 'input':
            default:
                return $this->renderInput($field);
        }
    }

    /** input text-like / file / date / number / etc */
    protected function renderInput(array $f): string
    {
        $type = $f['type'];
        $name = $f['name'];
        $label = (string)$f['label'];
        /** @var array<string,mixed> $o */
        $o = $f['opts'];

        $id = (string)($o['id'] ?? $name);
        $help = $o['help'];
        $error = $o['error'];

        $attrs = array_replace([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'value' => $o['value'],
            'placeholder' => $o['placeholder'],
        ], $o['attrs']);

        if ($o['required']) $attrs['required'] = true;
        if ($o['disabled']) $attrs['disabled'] = true;
        if ($o['readonly']) $attrs['readonly'] = true;
        if ($help)  $attrs['aria-describedby'] = ($attrs['aria-describedby'] ?? '') . " {$id}-help";
        if ($error) $attrs['aria-invalid'] = 'true';

        $html = [];
        $html[] = '  <div class="clform__group">';
        if ($label !== '') {
            $html[] = '    <label for="' . $this->escAttr($id) . '"' . $this->attrsToString($o['labelAttrs']) . '>' . $this->esc($label) . '</label>';
        }
        $valueOut = $o['raw'] ? (string)$attrs['value'] : $this->escAttr((string)($attrs['value'] ?? ''));
        // il value per input va in attributo, non innerHTML
        $attrs['value'] = $valueOut;
        $html[] = '    <input' . $this->attrsToString($attrs) . '>';

        if ($help)  $html[] = '    <div class="clform__help" id="' . $this->escAttr($id) . '-help">' . $this->esc((string)$help) . '</div>';
        if ($error) $html[] = '    <div class="clform__error">' . $this->esc((string)$error) . '</div>';
        $html[] = '  </div>';
        return implode("\n", $html);
    }

    protected function renderTextarea(array $f): string
    {
        $name  = $f['name'];
        $label = (string)$f['label'];
        /** @var array<string,mixed> $o */
        $o = $f['opts'];

        $id = (string)($o['id'] ?? $name);
        $help = $o['help'];
        $error = $o['error'];

        $attrs = array_replace([
            'id' => $id,
            'name' => $name,
            'rows' => $o['rows'],
            'placeholder' => $o['placeholder'],
        ], $o['attrs']);

        if ($o['required']) $attrs['required'] = true;
        if ($o['disabled']) $attrs['disabled'] = true;
        if ($o['readonly']) $attrs['readonly'] = true;
        if ($help)  $attrs['aria-describedby'] = ($attrs['aria-describedby'] ?? '') . " {$id}-help";
        if ($error) $attrs['aria-invalid'] = 'true';

        $value = (string)($o['value'] ?? '');
        $content = $o['raw'] ? $value : $this->esc($value);

        $html = [];
        $html[] = '  <div class="clform__group">';
        if ($label !== '') {
            $html[] = '    <label for="' . $this->escAttr($id) . '"' . $this->attrsToString($o['labelAttrs']) . '>' . $this->esc($label) . '</label>';
        }
        $html[] = '    <textarea' . $this->attrsToString($attrs) . '>' . $content . '</textarea>';
        if ($help)  $html[] = '    <div class="clform__help" id="' . $this->escAttr($id) . '-help">' . $this->esc((string)$help) . '</div>';
        if ($error) $html[] = '    <div class="clform__error">' . $this->esc((string)$error) . '</div>';
        $html[] = '  </div>';
        return implode("\n", $html);
    }

    protected function renderSelect(array $f): string
    {
        $name  = $f['name'];
        $label = (string)$f['label'];
        /** @var array<string,mixed> $o */
        $o = $f['opts'];
        $options = $f['options'];

        $id = (string)($o['id'] ?? $name);
        $help = $o['help'];
        $error = $o['error'];

        $attrs = array_replace([
            'id' => $id,
            'name' => $name . ($o['multiple'] ? '[]' : ''),
        ], $o['attrs']);
        if ($o['multiple']) $attrs['multiple'] = true;
        if ($o['required']) $attrs['required'] = true;
        if ($o['disabled']) $attrs['disabled'] = true;
        if ($help)  $attrs['aria-describedby'] = ($attrs['aria-describedby'] ?? '') . " {$id}-help";
        if ($error) $attrs['aria-invalid'] = 'true';

        $selected = $o['value'];
        $isMultiple = (bool)$o['multiple'];

        $html = [];
        $html[] = '  <div class="clform__group">';
        if ($label !== '') {
            $html[] = '    <label for="' . $this->escAttr($id) . '"' . $this->attrsToString($o['labelAttrs']) . '>' . $this->esc($label) . '</label>';
        }
        $html[] = '    <select' . $this->attrsToString($attrs) . '>';

        if ($o['placeholder'] !== null && !$isMultiple) {
            $html[] = '      <option value="">' . $this->esc((string)$o['placeholder']) . '</option>';
        }

        // opzioni semplici o optgroup
        if ($this->isOptGroups($options)) {
            foreach ($options as $grp) {
                $labelGrp = (string)($grp['label'] ?? '');
                $html[] = '      <optgroup label="' . $this->escAttr($labelGrp) . '">';
                foreach (($grp['options'] ?? []) as $val => $text) {
                    $sel = $this->isSelected($val, $selected, $isMultiple);
                    $html[] = '        <option value="' . $this->escAttr((string)$val) . '"' . ($sel ? ' selected' : '') . '>' . $this->esc((string)$text) . '</option>';
                }
                $html[] = '      </optgroup>';
            }
        } else {
            foreach ($options as $val => $text) {
                $sel = $this->isSelected($val, $selected, $isMultiple);
                $html[] = '      <option value="' . $this->escAttr((string)$val) . '"' . ($sel ? ' selected' : '') . '>' . $this->esc((string)$text) . '</option>';
            }
        }

        $html[] = '    </select>';
        if ($help)  $html[] = '    <div class="clform__help" id="' . $this->escAttr($id) . '-help">' . $this->esc((string)$help) . '</div>';
        if ($error) $html[] = '    <div class="clform__error">' . $this->esc((string)$error) . '</div>';
        $html[] = '  </div>';
        return implode("\n", $html);
    }

    protected function renderCheckbox(array $f): string
    {
        $name  = $f['name'];
        $label = (string)$f['label'];
        /** @var array<string,mixed> $o */
        $o = $f['opts'];

        $id = (string)($o['id'] ?? $name);
        $attrs = array_replace([
            'id' => $id,
            'name' => $name,
            'type' => 'checkbox',
            'value' => $o['value'],
        ], $o['attrs']);

        if ($o['checked']) $attrs['checked'] = true;
        if ($o['required']) $attrs['required'] = true;
        if ($o['disabled']) $attrs['disabled'] = true;

        $html = [];
        $html[] = '  <div class="clform__group clform__group--check">';
        if (!$o['labelAfter'] && $label !== '') {
            $html[] = '    <label for="' . $this->escAttr($id) . '"' . $this->attrsToString($o['labelAttrs']) . '>' . $this->esc($label) . '</label>';
        }
        $html[] = '    <input' . $this->attrsToString($attrs) . '>';
        if ($o['labelAfter'] && $label !== '') {
            $html[] = '    <label for="' . $this->escAttr($id) . '"' . $this->attrsToString($o['labelAttrs']) . '>' . $this->esc($label) . '</label>';
        }
        if (!empty($o['help']))  $html[] = '    <div class="clform__help">' . $this->esc((string)$o['help']) . '</div>';
        if (!empty($o['error'])) $html[] = '    <div class="clform__error">' . $this->esc((string)$o['error']) . '</div>';
        $html[] = '  </div>';
        return implode("\n", $html);
    }

    protected function renderRadioGroup(array $f): string
    {
        $name  = $f['name'];
        $label = (string)$f['label'];
        $choices = $f['choices'];
        /** @var array<string,mixed> $o */
        $o = $f['opts'];

        $idBase = (string)($o['id'] ?? $name);
        $selected = $o['value'];

        $html = [];
        $html[] = '  <div class="clform__group">';
        if ($label !== '') {
            $html[] = '    <div class="clform__label">' . $this->esc($label) . '</div>';
        }
        $wrapAttrs = array_replace(['class' => $o['inline'] ? 'clform__choices clform__choices--inline' : 'clform__choices'], $o['attrs']);
        $html[] = '    <div' . $this->attrsToString($wrapAttrs) . '>';

        $i = 0;
        foreach ($choices as $value => $text) {
            $choice = [
                'value' => is_array($text) ? ($text['value'] ?? $value) : $value,
                'label' => is_array($text) ? ($text['label'] ?? (string)$value) : $text,
                'attrs' => is_array($text) ? ($text['attrs'] ?? []) : [],
            ];
            $id = $idBase . '-' . $i++;
            $checked = ((string)$choice['value'] === (string)$selected);

            $attrs = array_replace([
                'type' => 'radio',
                'id' => $id,
                'name' => $name,
                'value' => $choice['value'],
            ], $choice['attrs']);
            if ($checked) $attrs['checked'] = true;
            if ($o['disabled']) $attrs['disabled'] = true;
            if ($o['required']) $attrs['required'] = true;

            $html[] = '      <label class="clform__choice">';
            $html[] = '        <input' . $this->attrsToString($attrs) . '>';
            $html[] = '        <span>' . $this->esc((string)$choice['label']) . '</span>';
            $html[] = '      </label>';
        }

        $html[] = '    </div>';
        if (!empty($o['help']))  $html[] = '    <div class="clform__help">' . $this->esc((string)$o['help']) . '</div>';
        if (!empty($o['error'])) $html[] = '    <div class="clform__error">' . $this->esc((string)$o['error']) . '</div>';
        $html[] = '  </div>';

        return implode("\n", $html);
    }

    protected function isOptGroups(mixed $options): bool
    {
        // euristica: se il primo elemento è array con chiave 'options'
        if (!is_array($options) || $options === []) return false;
        $first = reset($options);
        return is_array($first) && array_key_exists('options', $first);
    }

    protected function isSelected(string|int $val, mixed $selected, bool $multiple): bool
    {
        if ($multiple && is_array($selected)) {
            foreach ($selected as $s) {
                if ((string)$s === (string)$val) return true;
            }
            return false;
        }
        return (string)$selected === (string)$val;
    }

    protected function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    protected function escAttr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /** CSS integrato */
    protected function styleTag(): string
    {
        $css = <<<CSS
        <style>
        /* ====== CLForm base ====== */
        .clform{--c-border:#e5e7eb; --c-muted:#6b7280; --c-error:#b91c1c; --c-bg:#fff; --c-focus:#2563eb; --radius:10px; --pad:12px; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; font-size:14px; line-height:1.4; color:#111827; background:var(--c-bg);}
        .clform__group{margin-bottom:14px;}
        .clform__group label,.clform__label{display:block; font-weight:600; margin-bottom:6px;}
        .clform input[type="text"], .clform input[type="email"], .clform input[type="password"], .clform input[type="tel"], .clform input[type="url"], .clform input[type="number"], .clform input[type="date"], .clform input[type="file"], .clform select, .clform textarea{
        width:100%; padding:10px 12px; border:1px solid var(--c-border); border-radius:var(--radius); background:#fff; outline:none;
        }
        .clform textarea{min-height:96px;}
        .clform input:focus, .clform select:focus, .clform textarea:focus{
        border-color:var(--c-focus); box-shadow:0 0 0 3px rgba(37,99,235,.12);
        }
        .clform__help{margin-top:6px; color:var(--c-muted); font-size:12px;}
        .clform__error{margin-top:6px; color:var(--c-error); font-weight:600; font-size:12px;}
        /* checkbox & radio */
        .clform__group--check{display:flex; align-items:center; gap:8px;}
        .clform__choices{display:flex; flex-direction:column; gap:8px;}
        .clform__choices--inline{flex-direction:row; flex-wrap:wrap; gap:10px 16px;}
        .clform__choice{display:inline-flex; align-items:center; gap:8px; cursor:pointer;}
        /* actions */
        .clform__actions{display:flex; gap:10px; margin-top:18px;}
        .clform__actions button{
        padding:10px 14px; border-radius:10px; border:1px solid var(--c-border); background:#111827; color:#fff; cursor:pointer;
        }
        .clform__actions button[type="reset"]{background:#fff; color:#111827;}
        .clform__actions button:focus{outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.12);}
        </style>
        CSS;

        return $css;
    }
}
