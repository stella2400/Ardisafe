<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa

class IotAlert
{
    private ?int $id=null;
    private int $customerId=0;
    private string $severity='info'; // info|warning|critical
    private string $status='open';   // open|ack|closed
    private string $title='';
    private ?string $message=null;
    private ?string $createdAt=null;
    private ?string $resolvedAt=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }
    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['customer_id'])) $this->customerId=(int)$a['customer_id'];
        if (isset($a['severity'])) $this->severity=(string)$a['severity'];
        if (isset($a['status'])) $this->status=(string)$a['status'];
        if (isset($a['title'])) $this->title=(string)$a['title'];
        if (isset($a['message'])) $this->message = $a['message']!==null ? (string)$a['message'] : null;
        if (isset($a['created_at'])) $this->createdAt=(string)$a['created_at'];
        if (isset($a['resolved_at'])) $this->resolvedAt = $a['resolved_at']!==null ? (string)$a['resolved_at'] : null;
    }
    public function validate(): array {
        $e=[]; if ($this->customerId<=0) $e[]='customer_id obbligatorio';
        if ($this->title==='') $e[]='title obbligatorio';
        if (!in_array($this->severity,['info','warning','critical'],true)) $e[]='severity non valida';
        if (!in_array($this->status,['open','ack','closed'],true)) $e[]='status non valido';
        return $e;
    }
    public function toDb(): array {
        return ['customer_id'=>$this->customerId,'severity'=>$this->severity,'status'=>$this->status,'title'=>$this->title,'message'=>$this->message];
    }
}
