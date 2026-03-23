<?php
class IotAlarmEvent {
  protected ?int $id=null,$roomId=null,$deviceId=null;
  protected string $level='critical',$snapshotUrl='',$occurredAt='';
  public function assign(array $a): self {
    $this->id=(int)($a['id']??$this->id);
    $this->roomId=(int)($a['room_id']??$this->roomId);
    $this->deviceId= isset($a['device_id'])?(int)$a['device_id']:null;
    $this->level=(string)($a['level']??$this->level);
    $this->snapshotUrl=(string)($a['snapshot_url']??$this->snapshotUrl);
    $this->occurredAt=(string)($a['occurred_at']??$this->occurredAt);
    return $this;
  }
  public function getId(){return $this->id;} public function getSnapshot(){return $this->snapshotUrl;}
  public function getWhen(){return $this->occurredAt;} public function getLevel(){return $this->level;}
}
class IotAlarmEvents extends IotBaseRepository {
  public function listByRoom(int $roomId, int $limit=20): array {
    $st=$this->pdo->prepare('SELECT * FROM alarm_event WHERE room_id=? ORDER BY occurred_at DESC LIMIT ?');
    $st->bindValue(1,$roomId,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT);
    $st->execute(); $out=[]; foreach($st as $r){ $out[]=(new IotAlarmEvent())->assign($r); } return $out;
  }
}
