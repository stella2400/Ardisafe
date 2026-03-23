<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotEvent
{
    private ?int $id=null; // BIGINT
    private int $deviceId=0;
    private string $ts='';
    private string $eventType='motion';
    private ?array $payload=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['device_id'])) $this->deviceId=(int)$a['device_id'];
        if (isset($a['ts'])) $this->ts=(string)$a['ts'];
        if (isset($a['event_type'])) $this->eventType=(string)$a['event_type'];
        if (isset($a['payload'])) $this->payload = is_string($a['payload'])? json_decode($a['payload'],true):(is_array($a['payload'])?$a['payload']:null);
    }
    public function validate(): array {
        $e=[]; if ($this->deviceId<=0) $e[]='device_id obbligatorio';
        if ($this->ts==='') $e[]='ts obbligatorio';
        return $e;
    }
    public function toDb(): array {
        return ['device_id'=>$this->deviceId,'ts'=>$this->ts,'event_type'=>$this->eventType,'payload'=>$this->payload? json_encode($this->payload, JSON_UNESCAPED_UNICODE):null];
    }

    // setters rapidi
    public function setDeviceId(int $v): self { $this->deviceId=$v; return $this; }
    public function setTs(string $v): self { $this->ts=$v; return $this; }
    public function setEventType(string $v): self { $this->eventType=$v; return $this; }
    public function setPayload(?array $p): self { $this->payload=$p; return $this; }
}
