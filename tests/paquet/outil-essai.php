<?php

declare(strict_types=1);

// Outil de l'essai du paquet (copié dans le conteneur jetable, jamais dans le paquet) : renvoie l'adresse de la clé
// d'administration de l'essai en JSON, lue par tests/paquet/fumee.spec.ts et jamais affichée. Refusé ailleurs que
// dans l'essai (URL_PUBLIQUE = https://127.0.0.1:8096).
// Usage : php outil-essai.php /home/…/chores-app

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    exit(1);
}

require rtrim($argv[1], '/') . '/app/bootstrap.php';

$config = Chores\charger_configuration();
if ($config['URL_PUBLIQUE'] !== 'https://127.0.0.1:8096') {
    fwrite(STDERR, "Outil réservé à l'essai du paquet.\n");
    exit(1);
}
fwrite(STDOUT, json_encode(['cle' => Chores\url($config, '/admin/cle/' . $config['CLE_ADMIN'])], JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
