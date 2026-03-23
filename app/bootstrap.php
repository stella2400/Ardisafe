<?php
// Inizializza il layout condiviso con brand, logout e menù.
// NOTA: richiede che l'autoloader carichi CLAppLayout e CLButton.

function app(): CLAppLayout {
    static $app = null;
    if ($app === null) {
        // Costruisci lista menù:
        // - Visibili a tutti (roles => null)
        // - "Utenti" visibile SOLO a superuser
        $menu = [
        ['label'=>'Home', 'href'=>'/Ardisafe2.0/homepage.php'],
        ['label'=>'Profilo', 'href'=>'/Ardisafe2.0/profile.php'],
        ['label'=>'Impostazioni', 'href'=>'/Ardisafe2.0/settings.php'],
        ['label'=>'Report', 'href'=>'/Ardisafe2.0/reports.php'],
        ['label'=>'Stanze', 'href'=>'/Ardisafe2.0/rooms.php'],
        ['label'=>'Dispositivi', 'href'=>'/Ardisafe2.0/devices.php'],
        ['label'=>'Logout', 'href'=>'/Ardisafe2.0/logout.php'],
        ];

        $app = (new CLAppLayout())
            ->brand('<strong>ArdiSafe</strong>')
            ->menu($menu);
    }
    return $app;
}
