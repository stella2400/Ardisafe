<?php
require_once __DIR__ . '/config.php';

if (is_authenticated()) {
    header('Location: /Ardisafe2.0/homepage.php');
} else {
    header('Location: /Ardisafe2.0/login.php');
}
exit;
