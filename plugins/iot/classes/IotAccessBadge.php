<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';
class IotAccessBadge
{
    private ?int $id = null;
    private int $customerId = 0;
    private string $badgeCode = '';
    private string $pinHash = '';
    private string $status = 'active'; // active|revoked
    private ?string $lastUsed = null;

    public function __construct(array $a = []) { if ($a) $this->assign($a); }

    public function assign(array $a): void {
        if (isset($a['id']))          $this->id = (int)$a['id'];
        if (isset($a['customer_id'])) $this->customerId = (int)$a['customer_id'];
        if (isset($a['badge_code']))  $this->badgeCode = (string)$a['badge_code'];
        if (isset($a['pin_hash']))    $this->pinHash = (string)$a['pin_hash'];
        if (isset($a['status']))      $this->status = (string)$a['status'];
        if (isset($a['last_used']))   $this->lastUsed = $a['last_used'] !== null ? (string)$a['last_used'] : null;
    }

    public function setPin(string $pin): self { $this->pinHash = password_hash($pin, PASSWORD_DEFAULT); return $this; }
    public function verifyPin(string $pin): bool { return $this->pinHash !== '' && password_verify($pin, $this->pinHash); }

    public function validate(): array {
        $e=[]; if ($this->customerId<=0) $e[]='customer_id obbligatorio';
        if ($this->badgeCode==='') $e[]='badge_code obbligatorio';
        if ($this->pinHash==='') $e[]='pin_hash obbligatorio';
        if (!in_array($this->status,['active','revoked'],true)) $e[]='status non valido';
        return $e;
    }

    public function toDb(): array {
        return [
            'customer_id'=>$this->customerId,
            'badge_code'=>$this->badgeCode,
            'pin_hash'=>$this->pinHash,
            'status'=>$this->status,
            'last_used'=>$this->lastUsed
        ];
    }

    // ===== GETTER =====
    public function getId(): ?int { return $this->id; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getBadgeCode(): string { return $this->badgeCode; }
    public function getStatus(): string { return $this->status; }
    public function getLastUsed(): ?string { return $this->lastUsed; }
    public function getPinHash(): string { return $this->pinHash; }
}
