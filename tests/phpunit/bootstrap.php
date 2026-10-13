<?php

declare(strict_types=1);

// Amorce des tests PHPUnit : charge le noyau sans traiter de requête. Aucune base réelle n'est touchée :
// chaque test travaille sur une base SQLite en mémoire migrée (CasDeBase).

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/bootstrap.php';
