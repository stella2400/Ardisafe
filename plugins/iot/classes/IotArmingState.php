<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotArmingState
{
    private ?int $id=null;
    private int $customerId=0;
    private string $mode='disarmed'; // disarmed|home|away|night
    private ?string $updatedAt=null;
    private ?int $updatedBy=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['customer_id'])) $this->customerId=(int)$a['customer_id'];
        if (isset($a['mode'])) $this->mode=(string)$a['mode'];
        if (isset($a['updated_at'])) $this->updatedAt=(string)$a['updated_at'];
        if (isset($a['updated_by'])) $this->updatedBy = $a['updated_by']!==null ? (int)$a['updated_by'] : null;
    }

    public function validate(): array {
        $e=[]; if ($this->customerId<=0) $e[]='customer_id obbligatorio';
        if (!in_array($this->mode,['disarmed','home','away','night'],true)) $e[]='mode non valido';
        return $e;
    }

    public function toDb(): array {
        return ['customer_id'=>$this->customerId,'mode'=>$this->mode,'updated_by'=>$this->updatedBy];
    }

    // Getters
    public function getCustomerId(): int { return $this->customerId; }
    public function getMode(): string { return $this->mode; }
}
