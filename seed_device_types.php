<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';
$repo = new IotDeviceTypes();
$defs = [
  ['sensor','Generic','PIR',['metrics'=>['motion']]],
  ['sensor','Generic','DoorMagnet',['metrics'=>['open']]],
  ['actuator','Generic','Siren',['actions'=>['on','off']]],
  ['sensor','Generic','Thermo',['metrics'=>['temperature','humidity']]],
  ['camera','Generic','IPCam',['streams'=>['rtsp']]],
];
foreach ($defs as [$kind,$vendor,$model,$cap]) {
  if (!$repo->findBySignature($kind,$vendor,$model)) {
    $t = (new IotDeviceType())->assign([
      'kind'=>$kind,'vendor'=>$vendor,'model'=>$model,'capabilities'=>$cap
    ]);
    $repo->create($t);
  }
}
echo "OK\n";
