<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';
if (function_exists('require_auth')) { require_auth(); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$viewer  = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper = (isset($viewer['ruolo']) && $viewer['ruolo'] === 'superuser');

// ==== Load utente da modificare ====
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /Ardisafe2.0/users.php'); exit; }

$repoUsers = new CLUsers();
$user = $repoUsers->findById($id);
if (!$user) { header('Location: /Ardisafe2.0/users.php'); exit; }

// ==== CSRF token ====
if (empty($_SESSION['csrf'])) {
  try { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf'];

// ==== Messaggi UI ====
$messages = [];
$old = null; $openPwd = false;

// ==== POST handling ====
// Distinguiamo i form:
// - Form PROFILO/PASSWORD → campi standard (gestiti da CLUser::processEditPost)
// - Form BADGE (solo superuser) → hidden 'badge_action' = assign|revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isBadgePost = isset($_POST['badge_action']);
  if ($isBadgePost) {
    // ------ GESTIONE BADGE/PIN (solo superuser) ------
    if (!$isSuper) {
      $messages[] = ['type'=>'danger','text'=>'Permessi insufficienti per modificare badge/PIN.'];
    } elseif (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
      $messages[] = ['type'=>'danger','text'=>'Sessione scaduta o token CSRF non valido.'];
    } else {
      $repoB = new IotAccessBadges();
      $act   = (string)($_POST['badge_action'] ?? '');
      try {
        if ($act === 'assign') {
          $badge = trim((string)($_POST['badge_code'] ?? ''));
          $pin1  = (string)($_POST['pin'] ?? '');
          $pin2  = (string)($_POST['pin2'] ?? '');
          if ($badge === '') throw new InvalidArgumentException('Inserisci un codice badge.');
          if ($pin1 !== '' || $pin2 !== '') {
            if ($pin1 !== $pin2) throw new InvalidArgumentException('Le due password PIN non coincidono.');
            if (!preg_match('/^\d{4,10}$/', $pin1)) throw new InvalidArgumentException('PIN deve avere 4–10 cifre numeriche.');
          }
          $repoB->upsertForCustomer($user->getId(), $badge, $pin1 !== '' ? $pin1 : null);
          $messages[] = ['type'=>'success','text'=>'Badge/PIN salvati correttamente.'];
          $_SESSION['csrf'] = bin2hex(random_bytes(16)); // rigenera CSRF
          $csrf = $_SESSION['csrf'];
        } elseif ($act === 'revoke') {
          $repoB->revokeForCustomer($user->getId());
          $messages[] = ['type'=>'success','text'=>'Badge revocato.'];
        }
      } catch (Throwable $e) {
        $messages[] = ['type'=>'danger','text'=>$e->getMessage()];
      }
    }
  } else {
    // ------ GESTIONE PROFILO/PASSWORD ------
    $res = CLUser::processEditPost($repoUsers, $user, $_POST, $csrf, $isSuper);
    if ($res['ok'] && $res['redirect']) {
      header('Location: '.$res['redirect']); exit;
    }
    // re-render con errori/old/openPwd
    $messages = array_merge($messages, array_map(fn($e)=>['type'=>'danger','text'=>$e], $res['errors']));
    $openPwd  = $res['openPwd'] ?? false;
    $old      = $res['old'] ?? [];
  }
}

// ====== RENDER ======
echo app()->open('Modifica utente');

// blocco messaggi
if (!empty($messages)) {
  foreach ($messages as $m) {
    $cls = $m['type']==='success' ? 'alert-success' : 'alert-danger';
    echo '<div class="alert '.$cls.'" style="padding:10px 12px;border-radius:10px;margin-bottom:10px;">'.$m['text'].'</div>';
  }
}

// card profilo/password (usa CLUser::renderEdit)
echo $user->renderEdit([
  'actionUrl' => '/Ardisafe2.0/user_edit.php?id='.$user->getId(),
  'csrf'      => $csrf,
  'isSuper'   => $isSuper,
  'old'       => $old,
  'errors'    => [],
  'openPwd'   => $openPwd,
]);

// >>> DISTACCO TRA LE DUE CARD <<<
echo '<div style="height:16px"></div>';

// ====== Card Badge & PIN ======
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

if ($isSuper) {
  // Form di assegnazione/aggiornamento (badge + pin opzionale in update)
  $form = (new CLForm())
    ->start('/Ardisafe2.0/user_edit.php?id='.$user->getId(), 'POST', ['id'=>'badge-form'])
    ->csrf('csrf', $csrf)
    ->hidden('badge_action', 'assign')
    ->text('badge_code','Codice badge', [
      'required'=>true,
      'placeholder'=>'Es. 04A3F1C29B',
      'value' => $activeBadge ? $activeBadge->getBadgeCode() : ''
    ])
    ->password('pin','PIN (4–10 cifre)', [
      'placeholder'=>$activeBadge ? 'Lascia vuoto per non cambiarlo' : 'Imposta PIN',
      'attrs'=>['inputmode'=>'numeric','pattern'=>'[0-9]{4,10}']
    ])
    ->password('pin2','Conferma PIN', [
      'placeholder'=>$activeBadge ? 'Ripeti solo se hai inserito il PIN' : 'Ripeti PIN',
      'attrs'=>['inputmode'=>'numeric','pattern'=>'[0-9]{4,10}']
    ])
    ->submit($activeBadge ? 'Aggiorna badge/PIN' : 'Assegna badge/PIN', ['variant'=>'primary']);

  // Pulsante revoca
  $form2 = '';
  if ($activeBadge) {
    $form2 = (new CLForm())
      ->start('/Ardisafe2.0/user_edit.php?id='.$user->getId(), 'POST', ['style'=>'margin-top:8px'])
      ->csrf('csrf', $csrf)
      ->hidden('badge_action','revoke')
      ->submit('Revoca badge', ['variant'=>'secondary'])
      ->render();
  }

  $card = (new CLCard())->start()
    ->header('Badge & PIN', 'Gestione (solo superuser)')
    ->body($badgeInfo($activeBadge) . $form->render() . $form2, true);
} else {
  // Solo info se non superuser
  $card = (new CLCard())->start()
    ->header('Badge & PIN', 'Informazioni (sola lettura)')
    ->body($badgeInfo($activeBadge), true);
}

// stampa card
echo (new CLGrid())->start()->container('xl')->row(['g'=>20])
  ->colRaw(12, [], $card->render())
  ->endRow()->render();

echo app()->close();
