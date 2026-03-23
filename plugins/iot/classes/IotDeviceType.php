<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotDeviceType
{
    private ?int $id=null;
    private string $kind='sensor'; // sensor|actuator|camera|hub
    private ?string $vendor=null;
    private ?string $model=null;
    private ?array $capabilities=null; // JSON

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }

    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['kind'])) $this->kind=(string)$a['kind'];
        if (isset($a['vendor'])) $this->vendor = $a['vendor']!==null?(string)$a['vendor']:null;
        if (isset($a['model']))  $this->model  = $a['model']!==null?(string)$a['model']:null;
        if (isset($a['capabilities'])) {
            $this->capabilities = is_string($a['capabilities']) ? json_decode($a['capabilities'], true) : (is_array($a['capabilities'])?$a['capabilities']:null);
        }
    }

    public function validate(): array {
        $e=[]; if (!in_array($this->kind,['sensor','actuator','camera','hub'], true)) $e[]='kind non valido';
        return $e;
    }

    public function toDb(): array {
        return [
            'kind'=>$this->kind,
            'vendor'=>$this->vendor,
            'model'=>$this->model,
            'capabilities'=>$this->capabilities? json_encode($this->capabilities, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $v): self { $this->kind=$v; return $this; }
    public function getVendor(): ?string { return $this->vendor; }
    public function setVendor(?string $v): self { $this->vendor=$v; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $v): self { $this->model=$v; return $this; }
    public function getCapabilities(): ?array { return $this->capabilities; }
    public function setCapabilities(?array $c): self { $this->capabilities=$c; return $this; }
}
