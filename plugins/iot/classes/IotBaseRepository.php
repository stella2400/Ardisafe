<?php
declare(strict_types=1);

abstract class IotBaseRepository
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo instanceof PDO) { $this->pdo = $pdo; return; }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { $this->pdo = $GLOBALS['pdo']; return; }
        if (function_exists('db')) { $maybe = db(); if ($maybe instanceof PDO) { $this->pdo = $maybe; return; } }
        throw new RuntimeException('PDO non disponibile in IotBaseRepository');
    }

    protected function now(): string { return date('Y-m-d H:i:s'); }
}
