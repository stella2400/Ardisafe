<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';
if (function_exists('require_auth')) { require_auth(); }
if (($_SESSION['user']['ruolo'] ?? 'operator') !== 'superuser') { header('Location: /Ardisafe2.0/homepage.php'); exit; }

$repo = new CLUsers();
$q = trim($_GET['q'] ?? '');

app()->headExtra('<style>:root{--app-wrap-max: 1520px}</style>');

$filter = (new CLForm())
  ->start('/Ardisafe2.0/users.php','GET')
  ->text('q','', ['placeholder'=>'Cerca per nome, cognome o email','value'=>$q])
  ->submit('Cerca', ['variant'=>'primary'])
  ->render();

$tableHtml = $repo->renderTable($q);

$card = (new CLCard())->start()
  ->header('Utenti','Elenco completo dei clienti registrati')
  ->body('<div class="users-actions">'.$filter.'</div><div class="table-wrap">'.$tableHtml.'</div>', true);

echo app()->open('Utenti');
echo (new CLGrid())->start()->container('xl')->row()->colRaw(12, [], $card->render())->endRow()->render();
echo app()->close();
