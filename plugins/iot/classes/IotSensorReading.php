<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotSensorReading
{
    private ?int $id=null; // BIGINT in DB, lo teniamo int PHP
    private int $deviceId=0;
    private string $ts=''; // YYYY-MM-DD HH:MM:SS(.mmm)
    private string $metric='temperature';
    private ?float $valueNum=null;
    private ?string $valueTxt=null;
    private ?string $unit=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['device_id'])) $this->deviceId=(int)$a['device_id'];
        if (isset($a['ts'])) $this->ts=(string)$a['ts'];
        if (isset($a['metric'])) $this->metric=(string)$a['metric'];
        if (isset($a['value_num'])) $this->valueNum = $a['value_num']!==null ? (float)$a['value_num'] : null;
        if (isset($a['value_txt'])) $this->valueTxt = $a['value_txt']!==null ? (string)$a['value_txt'] : null;
        if (isset($a['unit'])) $this->unit = $a['unit']!==null ? (string)$a['unit'] : null;
    }

    public function validate(): array {
        $e=[]; if ($this->deviceId<=0) $e[]='device_id obbligatorio';
        if ($this->ts==='') $e[]='ts obbligatorio';
        return $e;
    }

    public function toDb(): array {
        return [
            'device_id'=>$this->deviceId, 'ts'=>$this->ts, 'metric'=>$this->metric,
            'value_num'=>$this->valueNum, 'value_txt'=>$this->valueTxt, 'unit'=>$this->unit
        ];
    }

    // Getters minimi
    public function getId(): ?int { return $this->id; }
    public function getDeviceId(): int { return $this->deviceId; }
    public function setDeviceId(int $v): self { $this->deviceId=$v; return $this; }
    public function setTs(string $v): self { $this->ts=$v; return $this; }
    public function setMetric(string $v): self { $this->metric=$v; return $this; }
    public function setValueNum(?float $v): self { $this->valueNum=$v; return $this; }
    public function setValueTxt(?string $v): self { $this->valueTxt=$v; return $this; }
    public function setUnit(?string $v): self { $this->unit=$v; return $this; }
}
