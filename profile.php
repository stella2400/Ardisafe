<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';

$id = $_SESSION['user']['id'];
header('Location: /Ardisafe2.0/user_view.php?id='.$id);