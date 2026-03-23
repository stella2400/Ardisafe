<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotCameraFrame
{
    private ?int $id=null; // BIGINT
    private int $deviceId=0;
    private string $ts='';
    private string $path='';
    private ?string $thumbPath=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['device_id'])) $this->deviceId=(int)$a['device_id'];
        if (isset($a['ts'])) $this->ts=(string)$a['ts'];
        if (isset($a['path'])) $this->path=(string)$a['path'];
        if (isset($a['thumb_path'])) $this->thumbPath = $a['thumb_path']!==null ? (string)$a['thumb_path'] : null;
    }
    public function validate(): array {
        $e=[]; if ($this->deviceId<=0) $e[]='device_id obbligatorio';
        if ($this->ts==='') $e[]='ts obbligatorio';
        if ($this->path==='') $e[]='path obbligatorio';
        return $e;
    }
    public function toDb(): array {
        return ['device_id'=>$this->deviceId,'ts'=>$this->ts,'path'=>$this->path,'thumb_path'=>$this->thumbPath];
    }
}
