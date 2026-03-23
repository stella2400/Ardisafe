<?php
class IotRoomEntry {
  protected string $userName='', $when='';
  public function assign(array $a): self { $this->userName=(string)$a['user_name']; $this->when=(string)$a['occurred_at']; return $this; }
  public function getUser(){return $this->userName;} public function getWhen(){return $this->when;}
}
class IotRoomEntries extends IotBaseRepository {
  public function listByRoom(int $roomId, int $limit=20): array {
    $sql='SELECT CONCAT(c.nome," ",c.cognome) AS user_name, ral.occurred_at
          FROM room_access_log ral
          JOIN customer c ON c.id=ral.customer_id
          WHERE ral.room_id=?
          ORDER BY ral.occurred_at DESC LIMIT ?';
    $st=$this->pdo->prepare($sql);
    $st->bindValue(1,$roomId,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT);
    $st->execute(); $out=[]; foreach($st as $r){ $out[]=(new IotRoomEntry())->assign($r);} return $out;
  }
}
