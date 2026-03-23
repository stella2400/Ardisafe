<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';
if (function_exists('require_auth')) require_auth();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();

if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  header('Location: /Ardisafe2.0/rooms.php?err=csrf'); exit;
}
$viewer  = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper = ($viewer['ruolo']??'operator')==='superuser';
if (!$isSuper){ header('Location: /Ardisafe2.0/rooms.php?err=perm'); exit; }

$roomId = (int)($_POST['room_id']??0);
$to     = (string)($_POST['to']??'');
if ($roomId<=0 || !in_array($to,['disarmed','armed','triggered'],true)){
  header('Location: /Ardisafe2.0/rooms.php?err=bad'); exit;
}

$repoRoom = new IotRooms();
$room = $repoRoom->findById($roomId);
if (!$room || $room->getCustomerId() !== (int)($_SESSION['user']['id']??0)) {
  header('Location: /Ardisafe2.0/rooms.php?err=notfound'); exit;
}

$alarms = new IotRoomAlarms();
$alarms->setState($roomId, $to);

$ref = !empty($_POST['ref']) ? $_POST['ref'] : "/Ardisafe2.0/room_view.php?id={$roomId}";
header("Location: ".$ref);
exit;
