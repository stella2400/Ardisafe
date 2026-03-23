<?php
/**
 * Autoloader.php
 * Carica automaticamente tutti i file PHP presenti in una directory (anche sottocartelle).
 */

spl_autoload_register(function ($class) {
    // Definisci la cartella base dove si trovano le tue classi
    $baseDir = __DIR__;

    // Nome file atteso
    $file = $baseDir . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Se invece vuoi includere TUTTI i file PHP presenti sotto una cartella,
 * puoi usare questa funzione ricorsiva.
 */
function require_all($dir)
{
    $scan = scandir($dir);
    foreach ($scan as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            require_all($path); // Ricorsione per le sottocartelle
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            require_once $path;
        }
    }
}

// Se vuoi includere tutti i file subito
require_all(__DIR__.'/lib');
require_all(__DIR__.'/plugins/customer');
require_all(__DIR__.'/plugins/iot');
// Se usi una semplice PSR-0/4 che carica /lib/classes/CL*.php, CLAppLayout verrà caricato automaticamente.
// In ogni caso, includi il bootstrap per inizializzare il layout condiviso:
// Bootstrap layout condiviso
$__bootstrap = __DIR__ . '/app/bootstrap.php';
if (file_exists($__bootstrap)) require_once $__bootstrap;

// Guard di sessione (idle timeout server-side)
$__guard = __DIR__ . '/app/session_guard.php';
if (file_exists($__guard)) require_once $__guard;
