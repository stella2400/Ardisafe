<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotDevice
{
    private ?int $id=null;
    private int $customerId=0;
    private ?int $roomId=null;
    private int $typeId=0;
    private string $name='';
    private string $ident='';
    private string $status='unknown'; // online|offline|unknown
    private ?string $lastSeen=null;
    private ?array $meta=null;
    private ?string $createdAt=null;
    private ?string $updatedAt=null;

    public function __construct(array $a=[]) { if ($a) $this->assign($a); }

    public function assign(array $a): void {
        if (isset($a['id'])) $this->id=(int)$a['id'];
        if (isset($a['customer_id'])) $this->customerId=(int)$a['customer_id'];
        if (isset($a['room_id'])) $this->roomId = $a['room_id']!==null ? (int)$a['room_id'] : null;
        if (isset($a['type_id'])) $this->typeId=(int)$a['type_id'];
        if (isset($a['name'])) $this->name=(string)$a['name'];
        if (isset($a['ident'])) $this->ident=(string)$a['ident'];
        if (isset($a['status'])) $this->status=(string)$a['status'];
        if (isset($a['last_seen'])) $this->lastSeen = $a['last_seen']? (string)$a['last_seen']:null;
        if (isset($a['meta'])) $this->meta = is_string($a['meta'])? json_decode($a['meta'], true) : (is_array($a['meta'])? $a['meta'] : null);
        if (isset($a['created_at'])) $this->createdAt=(string)$a['created_at'];
        if (isset($a['updated_at'])) $this->updatedAt=(string)$a['updated_at'];
    }

    public function validate(): array {
        $e=[];
        if ($this->customerId<=0) $e[]='customer_id obbligatorio';
        if ($this->typeId<=0) $e[]='type_id obbligatorio';
        if ($this->name==='') $e[]='name obbligatorio';
        if ($this->ident==='') $e[]='ident obbligatorio';
        if (!in_array($this->status,['online','offline','unknown'],true)) $e[]='status non valido';
        return $e;
    }

    public function toDb(): array {
        return [
            'customer_id'=>$this->customerId,
            'room_id'=>$this->roomId,
            'type_id'=>$this->typeId,
            'name'=>$this->name,
            'ident'=>$this->ident,
            'status'=>$this->status,
            'last_seen'=>$this->lastSeen,
            'meta'=>$this->meta? json_encode($this->meta, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    // getters/setters essenziali
    public function getId(): ?int { return $this->id; }
    public function getCustomerId(): int { return $this->customerId; }
    public function setCustomerId(int $v): self { $this->customerId=$v; return $this; }
    public function getRoomId(): ?int { return $this->roomId; }
    public function setRoomId(?int $v): self { $this->roomId=$v; return $this; }
    public function getTypeId(): int { return $this->typeId; }
    public function setTypeId(int $v): self { $this->typeId=$v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name=$v; return $this; }
    public function getIdent(): string { return $this->ident; }
    public function setIdent(string $v): self { $this->ident=$v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status=$v; return $this; }
    public function getLastSeen(): ?string { return $this->lastSeen; }
    public function setLastSeen(?string $v): self { $this->lastSeen=$v; return $this; }
    public function getMeta(): ?array { return $this->meta; }
    public function setMeta(?array $m): self { $this->meta=$m; return $this; }
}
