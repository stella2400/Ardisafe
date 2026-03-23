<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/autoloader.php';
if (function_exists('require_auth')) require_auth();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** Ricava l’ID del customer corrente in modo robusto */
function current_customer_id(): int {
  if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  $email = $_SESSION['user']['email'] ?? null;
  if (!$email) return 0;

  // PDO affidabile: usa $pdo globale se presente, altrimenti fallback
  $pdo = $GLOBALS['pdo'] ?? null;
  if (!$pdo instanceof PDO) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : 'ardisafe';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn  = defined('DB_DSN')  ? DB_DSN  : "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo  = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }

  $st = $pdo->prepare('SELECT id, ruolo FROM customer WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $row = $st->fetch();
  if ($row) {
    $_SESSION['user']['id']    = (int)$row['id'];
    $_SESSION['user']['ruolo'] = $row['ruolo'] ?? ($_SESSION['user']['ruolo'] ?? 'operator');
    return (int)$row['id'];
  }
  return 0;
}

$viewer      = $_SESSION['user'] ?? ['ruolo'=>'operator'];
$isSuper     = ($viewer['ruolo'] ?? 'operator') === 'superuser';
$customerId  = current_customer_id();
if (!$isSuper || $customerId <= 0) { header('Location: /Ardisafe2.0/rooms.php'); exit; }

$id   = (int)($_GET['id'] ?? 0);
$repo = new IotRooms();
$room = $id>0 ? $repo->findById($id) : new IotRoom(['customer_id'=>$customerId]);
if ($id && (!$room || $room->getCustomerId() !== $customerId)) { header('Location: /Ardisafe2.0/rooms.php'); exit; }

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$errors = [];
$notice = '';

/* ================== POST ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf']) || !hash_equals($csrf, (string)$_POST['csrf'])) {
    $errors[] = 'Sessione scaduta. Riprova.';
  } else {
    $name  = trim((string)($_POST['name'] ?? ''));
    $floor = trim((string)($_POST['floor_label'] ?? ''));

    if ($name === '') $errors[] = 'Il nome stanza è obbligatorio.';

    // Stato immagini
    $oldImage = $room?->getImage(); // URL precedente (se esiste)
    $newUrl   = null;               // URL della nuova immagine
    $newFs    = null;               // path FS della nuova immagine

    // Upload opzionale (campo file: image_file)
    if (isset($_FILES['image_file']) && is_array($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
      $f = $_FILES['image_file'];

      if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore di upload (codice '.$f['error'].').';
      } else {
        $tmp  = $f['tmp_name'];
        $size = (int)$f['size'];

        if (!is_uploaded_file($tmp)) {
          $errors[] = 'Upload non valido.';
        } else {
          if ($size > 8 * 1024 * 1024) {
            $errors[] = 'L’immagine supera 8 MB.';
          } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp) ?: '';
            $map   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            if (!isset($map[$mime])) {
              $errors[] = 'Formato immagine non supportato (JPG/PNG/WEBP/GIF).';
            } else {
              $ext = $map[$mime];
              $uploadDir = __DIR__ . '/image/room';
              if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
              if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                $errors[] = 'La cartella di upload non è scrivibile: /image/room';
              } else {
                try {
                  $unique = 'room_'.$customerId.'_'.bin2hex(random_bytes(6)).'.'.$ext;
                } catch (Throwable $e) {
                  $unique = 'room_'.$customerId.'_'.uniqid('', true).'.'.$ext;
                }
                $destDisk = $uploadDir . '/' . $unique;

                if (!move_uploaded_file($tmp, $destDisk)) {
                  $errors[] = 'Impossibile spostare il file caricato.';
                } else {
                  $newFs  = $destDisk;
                  $newUrl = '/Ardisafe2.0/image/room/' . $unique; // URL pubblico da salvare a DB
                }
              }
            }
          }
        }
      }
    }

    // Se errori, ed eventualmente cancelliamo il file appena caricato (rollback)
    if (!empty($errors)) {
      if ($newFs && is_file($newFs)) @unlink($newFs);
    } else {
      // Assegna i campi al model (usa la nuova immagine se esiste, altrimenti mantieni la vecchia)
      $room->assign([
        'customer_id' => $customerId,
        'name'        => $name,
        'floor_label' => ($floor !== '') ? $floor : null,
        'image'       => $newUrl ?? $oldImage,
      ]);

      try {
        if ($id > 0) {
          $repo->update($room);
        } else {
          $id = $repo->create($room);
        }

        // Successo: se abbiamo caricato una immagine nuova, elimina la vecchia (solo se è nella cartella dedicata)
        if ($newUrl && $oldImage && $oldImage !== $newUrl) {
          if (strncmp($oldImage, '/Ardisafe2.0/image/room/', 24) === 0) {
            $oldFs = __DIR__ . '/image/room/' . basename($oldImage);
            if (is_file($oldFs)) @unlink($oldFs);
          }
        }

        header('Location: /Ardisafe2.0/rooms.php?ok=1');
        exit;

      } catch (Throwable $e) {
        // Rollback: se DB fallisce ma il file nuovo c'è, cancellalo per non lasciare orfani
        if ($newFs && is_file($newFs)) @unlink($newFs);
        $errors[] = 'Errore di salvataggio. Riprova.';
      }
    }
  }
}

/* ================== RENDER ================== */
echo app()->open($id>0 ? 'Modifica stanza' : 'Nuova stanza');

$msg = '';
foreach ($errors as $e) $msg .= '<div class="alert alert-danger" style="padding:10px;border-radius:10px;margin-bottom:10px">'.htmlspecialchars($e).'</div>';
if ($notice) $msg .= '<div class="alert alert-success" style="padding:10px;border-radius:10px;margin-bottom:10px">'.htmlspecialchars($notice).'</div>';

$preview = '';
if ($room && $room->getImage()) {
  $src = htmlspecialchars($room->getImage(), ENT_QUOTES, 'UTF-8');
  $preview = '<div style="margin-top:8px"><img src="'.$src.'" alt="Anteprima" style="max-width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)"></div>';
}

/** Form con CLForm: file come PRIMO campo */
$form = (new CLForm())
  ->start('/Ardisafe2.0/room_edit.php'.($id?('?id='.$id):''), 'POST', [
      'id'=>'room-form',
      // enctype verrà impostato automaticamente grazie al campo file()
  ])
  ->csrf('csrf', $csrf)

  // 1) Immagine stanza (file)
  ->file('image_file','Immagine stanza', [
      'accept' => 'image/*',
      'hint'   => 'JPG/PNG/WEBP/GIF, max 8MB. Il file verrà caricato in /image/room e salvato a DB.',
  ])
  ->raw($preview)

  // 2) Nome stanza
  ->text('name','Nome stanza', [
      'required'=>true,
      'value'=>htmlspecialchars($room?->getName() ?? '', ENT_QUOTES, 'UTF-8'),
  ])

  // 3) Piano/Livello
  ->text('floor_label','Piano/Livello', [
      'placeholder'=>'Es. Piano terra',
      'value'=>htmlspecialchars($room?->getFloorLabel() ?? '', ENT_QUOTES, 'UTF-8'),
  ])

  ->submit($id>0?'Salva':'Crea', ['variant'=>'primary']);

$card = (new CLCard())->start()
  ->header($id>0?'Modifica stanza':'Nuova stanza')
  ->body($msg.$form->render(), true);

echo (new CLGrid())->start()->container('xl')
  ->row(['g'=>20])->colRaw(12,[],$card->render())->endRow()
  ->render();

echo app()->close();
