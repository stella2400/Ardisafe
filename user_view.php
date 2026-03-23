<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';
if (function_exists('require_auth')) { require_auth(); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$viewer   = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper  = (isset($viewer['ruolo']) && $viewer['ruolo'] === 'superuser');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /Ardisafe2.0/users.php'); exit; }

$usersRepo = new CLUsers();
$user = $usersRepo->findById($id);
if (!$user) { header('Location: /Ardisafe2.0/users.php'); exit; }

echo app()->open('Dettaglio utente');

// Card informativa Badge & PIN
$repoB = new IotAccessBadges();
$activeBadge = $repoB->findActiveByCustomer($user->getId());

$badgeInfo = function(?IotAccessBadge $b): string {
  if (!$b) return '<p>Nessun badge attivo associato.</p>';
  $code = htmlspecialchars($b->getBadgeCode(), ENT_QUOTES,'UTF-8');
  $len  = mb_strlen($code, 'UTF-8');
  $masked = $len > 4 ? str_repeat('•', $len - 4) . mb_substr($code, -4, null, 'UTF-8') : str_repeat('•', $len);
  $last = $b->getLastUsed() ? date('d/m/Y H:i', strtotime($b->getLastUsed())) : '—';
  return '<ul style="margin:.2rem 0 0 1rem">
    <li>Codice badge: <strong>'.$masked.'</strong></li>
    <li>Stato: <strong>attivo</strong></li>
    <li>Ultimo utilizzo: <strong>'.$last.'</strong></li>
    <li>PIN: <em>non visualizzabile</em></li>
  </ul>';
};


// SPACER tra le due righe (distacco visibile)
$spacer = '<div style="height:14px"></div>';
$badgeCardHtml = (new CLCard())->start()
  ->header('Badge & PIN', 'Informazioni credenziali fisiche')
  ->body($badgeInfo($activeBadge), true)
  ->render();

// Render con layout a 2 righe 8/4 come nello screenshot
echo $user->renderView([
  'canEdit'         => $isSuper || (($viewer['id'] ?? null) === $user->getId()),
  'showEmailButton' => true,
  'rightAsideHtml'  => $badgeCardHtml,   // appare nella colonna destra sotto
]);

echo app()->close();
