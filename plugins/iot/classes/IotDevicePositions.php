<?php
class IotDevicePosition {
  protected ?int $id=null, $roomId=null, $deviceId=null;
  protected int $xPct=50, $yPct=50;
  public function assign(array $a): self {
    foreach (['id','room_id','device_id','x_pct','y_pct'] as $k) if (isset($a[$k])) {
      $prop = str_replace('_','', ucwords($k,'_')); $prop[0]=strtolower($prop[0]);
    }
    $this->id       = isset($a['id']) ? (int)$a['id'] : $this->id;
    $this->roomId   = isset($a['room_id']) ? (int)$a['room_id'] : $this->roomId;
    $this->deviceId = isset($a['device_id']) ? (int)$a['device_id'] : $this->deviceId;
    $this->xPct     = isset($a['x_pct']) ? (int)$a['x_pct'] : $this->xPct;
    $this->yPct     = isset($a['y_pct']) ? (int)$a['y_pct'] : $this->yPct;
    return $this;
  }
  public function getId(){return $this->id;} public function getRoomId(){return $this->roomId;}
  public function getDeviceId(){return $this->deviceId;} public function getX(){return $this->xPct;}
  public function getY(){return $this->yPct;}
}

class IotDevicePositions extends IotBaseRepository {
  public function listByRoom(int $roomId): array {
    $st = $this->pdo->prepare('SELECT * FROM device_position WHERE room_id=?');
    $st->execute([$roomId]); $out=[];
    foreach ($st as $row){ $o=(new IotDevicePosition())->assign($row); $out[$o->getDeviceId()]=$o; }
    return $out;
  }
  public function upsert(int $roomId, int $deviceId, int $x, int $y): void {
    $st = $this->pdo->prepare('SELECT id FROM device_position WHERE room_id=? AND device_id=?');
    $st->execute([$roomId,$deviceId]); $id = $st->fetchColumn();
    if ($id) {
      $u = $this->pdo->prepare('UPDATE device_position SET x_pct=?, y_pct=? WHERE id=?');
      $u->execute([$x,$y,$id]);
    } else {
      $i = $this->pdo->prepare('INSERT INTO device_position(room_id,device_id,x_pct,y_pct) VALUES(?,?,?,?)');
      $i->execute([$roomId,$deviceId,$x,$y]);
    }
  }
}
