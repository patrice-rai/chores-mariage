<?php

declare(strict_types=1);

// Point d'entrée unique du site. Le code vit hors racine web : à côté de public/ en local, ailleurs chez
// l'hébergeur si public/chemin-app.php (non versionné) donne le chemin du dossier app/.

const CHORES_PUBLIC = __DIR__;

$cheminApp = __DIR__ . '/../app';
if (is_file(__DIR__ . '/chemin-app.php')) {
    $cheminApp = (string) require __DIR__ . '/chemin-app.php';
}

require $cheminApp . '/bootstrap.php';

\Chores\demarrer();
