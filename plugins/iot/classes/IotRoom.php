<?php
declare(strict_types=1);

class IotRoom
{
    private ?int $id = null;
    private int $customerId = 0;
    private string $name = '';
    private ?string $floorLabel = null;
    private ?string $image = null;         // URL / path relativo es. /Ardisafe2.0/image/room/xxx.webp
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    /** Costruttore opzionale con assign */
    public function __construct(array $data = [])
    {
        if ($data) $this->assign($data);
    }

    /** Assegna dati da array (accetta sia snake_case che nomi camel compatibili) */
    public function assign(array $a): void
    {
        if (isset($a['id']))             $this->id = (int)$a['id'];
        if (isset($a['customer_id']))    $this->customerId = (int)$a['customer_id'];
        if (isset($a['customerId']))     $this->customerId = (int)$a['customerId'];
        if (isset($a['name']))           $this->name = (string)$a['name'];
        if (array_key_exists('floor_label', $a)) $this->floorLabel = $a['floor_label'] !== null ? (string)$a['floor_label'] : null;
        if (array_key_exists('floorLabel', $a))  $this->floorLabel = $a['floorLabel'] !== null ? (string)$a['floorLabel'] : null;
        if (array_key_exists('image', $a))       $this->image = $a['image'] !== null ? (string)$a['image'] : null;
        if (isset($a['created_at']))     $this->createdAt = (string)$a['created_at'];
        if (isset($a['updated_at']))     $this->updatedAt = (string)$a['updated_at'];
    }

    /** Validazione semplice; ritorna array di errori (vuoto se ok) */
    public function validate(): array
    {
        $err = [];
        if ($this->customerId <= 0)        $err[] = 'Customer mancante.';
        if (trim($this->name) === '')      $err[] = 'Il nome stanza è obbligatorio.';
        if ($this->image !== null && strlen($this->image) > 255) $err[] = 'URL immagine troppo lungo.';
        return $err;
    }

    // -------- Get/Set --------
    public function getId(): ?int { return $this->id; }
    public function setId(?int $v): void { $this->id = $v; }

    public function getCustomerId(): int { return $this->customerId; }
    public function setCustomerId(int $v): void { $this->customerId = $v; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): void { $this->name = $v; }

    public function getFloorLabel(): ?string { return $this->floorLabel; }
    public function setFloorLabel(?string $v): void { $this->floorLabel = $v !== '' ? $v : null; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $v): void { $this->image = $v !== '' ? $v : null; }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }

    /** Utility: costruisce l’entity da una row DB */
    public static function fromRow(array $row): self
    {
        return new self([
            'id'          => $row['id'] ?? null,
            'customer_id' => $row['customer_id'] ?? null,
            'name'        => $row['name'] ?? '',
            'floor_label' => $row['floor_label'] ?? null,
            'image'       => $row['image'] ?? null,
            'created_at'  => $row['created_at'] ?? null,
            'updated_at'  => $row['updated_at'] ?? null,
        ]);
    }

    /** Array per persistenza */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customerId,
            'name'        => $this->name,
            'floor_label' => $this->floorLabel,
            'image'       => $this->image,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
