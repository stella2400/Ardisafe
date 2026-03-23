<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotAiDetection
{
    private ?int $id=null; // BIGINT
    private int $frameId=0;
    private string $label='person';
    private float $confidence=0.0;
    private ?array $bbox=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['frame_id'])) $this->frameId=(int)$a['frame_id'];
        if (isset($a['label'])) $this->label=(string)$a['label'];
        if (isset($a['confidence'])) $this->confidence=(float)$a['confidence'];
        if (isset($a['bbox'])) $this->bbox = is_string($a['bbox'])? json_decode($a['bbox'], true):(is_array($a['bbox'])?$a['bbox']:null);
    }
    public function validate(): array {
        $e=[]; if ($this->frameId<=0) $e[]='frame_id obbligatorio';
        if ($this->label==='') $e[]='label obbligatoria';
        if ($this->confidence<0 || $this->confidence>1) $e[]='confidence 0..1';
        return $e;
    }
    public function toDb(): array {
        return ['frame_id'=>$this->frameId,'label'=>$this->label,'confidence'=>$this->confidence,'bbox'=>$this->bbox? json_encode($this->bbox, JSON_UNESCAPED_UNICODE):null];
    }
}
