<?php
declare(strict_types=1);
require_once __DIR__ . '/IotBaseRepository.php';   // << aggiungi questa
class IotSensorReadings extends IotBaseRepository
{
    public function create(IotSensorReading $r): int {
        $err=$r->validate(); if ($err) throw new InvalidArgumentException(implode('; ',$err));
        $a=$r->toDb();
        $sql='INSERT INTO sensor_reading(device_id,ts,metric,value_num,value_txt,unit)
              VALUES (:device_id,:ts,:metric,:value_num,:value_txt,:unit)';
        $this->pdo->prepare($sql)->execute($a);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByDevice(int $deviceId, ?string $from=null, ?string $to=null, ?string $metric=null, int $limit=500): array {
        $sql='SELECT * FROM sensor_reading WHERE device_id=?';
        $par=[$deviceId];
        if ($metric){ $sql.=' AND metric=?'; $par[]=$metric; }
        if ($from){ $sql.=' AND ts >= ?'; $par[]=$from; }
        if ($to){ $sql.=' AND ts <= ?'; $par[]=$to; }
        $sql.=' ORDER BY ts DESC LIMIT '.$limit;
        $st=$this->pdo->prepare($sql); $st->execute($par);
        return array_map(fn($r)=>new IotSensorReading($r), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function lastByDeviceMetric(int $deviceId, string $metric): ?IotSensorReading {
        $st=$this->pdo->prepare('SELECT * FROM sensor_reading WHERE device_id=? AND metric=? ORDER BY ts DESC LIMIT 1');
        $st->execute([$deviceId,$metric]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return $row? new IotSensorReading($row) : null;
    }
}
