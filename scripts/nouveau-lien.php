<?php

declare(strict_types=1);

// Crée un nouveau lien secret (32 octets aléatoires), range son empreinte SHA-256, incrémente la version :
// l'ancien lien et tous les cookies d'accès tombent aussitôt. Affiche le lien complet, à transmettre aux cousins.
// Usage (dans le conteneur ou en SSH chez l'hébergeur) : php scripts/nouveau-lien.php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

Chores\prendre_identite_du_stockage();

if (Chores\en_maintenance()) {
    fwrite(STDERR, "Le site est en maintenance (restauration en cours) : réessaie dans une minute.\n");
    exit(1);
}

try {
    $config = Chores\charger_configuration();
    $pdo = Chores\connexion();
    Chores\migrer($pdo);
    $jeton = Chores\nouveau_jeton($pdo);
    $version = Chores\version_lien($pdo);
    Chores\journaliser($pdo, 'admin_lien', null, 'version ' . $version . ' (script)');
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Lien non créé : ' . $erreur->getMessage() . "\n");
    exit(1);
}

$hote = $config['ENV'] === 'dev' ? '127.0.0.1:8095' : '';
fwrite(STDOUT, "Nouveau lien (version {$version}) : l'ancien ne marche plus.\n");
fwrite(STDOUT, Chores\lien_complet($config, $jeton, $hote) . "\n");
exit(0);
