<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';
class IotRoomAlarm
{
    private ?int $id = null;
    private int $customerId = 0;
    private int $roomId = 0;
    private string $state = 'disarmed'; // disarmed | armed | triggered
    private ?string $updatedAt = null;

    public function __construct(array $a = []) { if ($a) $this->assign($a); }

    public function assign(array $a): void {
        if (isset($a['id'])) $this->id = (int)$a['id'];
        if (isset($a['customer_id'])) $this->customerId = (int)$a['customer_id'];
        if (isset($a['room_id'])) $this->roomId = (int)$a['room_id'];
        if (isset($a['state'])) $this->state = in_array($a['state'], ['disarmed','armed','triggered'], true) ? $a['state'] : 'disarmed';
        if (isset($a['updated_at'])) $this->updatedAt = (string)$a['updated_at'];
    }

    public static function fromRow(array $r): self {
        return new self([
            'id' => $r['id'] ?? null,
            'customer_id' => $r['customer_id'] ?? 0,
            'room_id' => $r['room_id'] ?? 0,
            'state' => $r['state'] ?? 'disarmed',
            'updated_at' => $r['updated_at'] ?? null,
        ]);
    }

    public function validate(): array {
        $e = [];
        if ($this->customerId <= 0) $e[] = 'customerId mancante';
        if ($this->roomId <= 0) $e[] = 'roomId mancante';
        if (!in_array($this->state, ['disarmed','armed','triggered'], true)) $e[] = 'state non valido';
        return $e;
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }
    public function setId(?int $v): void { $this->id = $v; }
    public function getCustomerId(): int { return $this->customerId; }
    public function setCustomerId(int $v): void { $this->customerId = $v; }
    public function getRoomId(): int { return $this->roomId; }
    public function setRoomId(int $v): void { $this->roomId = $v; }
    public function getState(): string { return $this->state; }
    public function setState(string $v): void { $this->state = in_array($v, ['disarmed','armed','triggered'], true) ? $v : 'disarmed'; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }
}
