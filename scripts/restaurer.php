<?php

declare(strict_types=1);

// Restaure la base depuis une sauvegarde (docs/deploy/sauvegardes.md). Contrôle d'abord le fichier (base SQLite
// intègre, avec les tables du site), garde le lien en service, copie la base actuelle dans
// stockage/sauvegardes/avant-restauration/, met la sauvegarde en place d'un seul geste sous le témoin de maintenance,
// puis applique les migrations manquantes. Les vidéos ne sont pas touchées.
// Usage (en SSH) : php scripts/restaurer.php stockage/sauvegardes/base-AAAA-MM-JJ-HHMMSS.sqlite --oui

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

Chores\prendre_identite_du_stockage();

$fichier = $argv[1] ?? '';
if ($fichier === '' || !in_array('--oui', array_slice($argv, 2), true)) {
    fwrite(STDERR, "Usage : php scripts/restaurer.php <sauvegarde.sqlite> --oui\n"
        . "« --oui » confirme que la base actuelle sera remplacée (elle est d'abord copiée à côté).\n");
    exit(1);
}
$chemin = realpath($fichier);
if ($chemin === false) {
    fwrite(STDERR, "Sauvegarde introuvable : {$fichier}\n");
    exit(1);
}

try {
    $resultat = Chores\restaurer_base($chemin, Chores\chemin_base());
} catch (Chores\RestaurationPartielle $erreur) {
    fwrite(STDERR, $erreur->getMessage() . "\nLance « php scripts/migrer.php » pour finir la mise à jour de la base.\n");
    exit(1);
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Restauration refusée (la base actuelle n’a pas changé) : ' . $erreur->getMessage() . "\n");
    exit(1);
}

try {
    Chores\journaliser(Chores\connexion(), 'admin_modif', null, 'restauration de ' . basename($chemin));
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Restauration faite, mais le journal n’a pas pu l’écrire : ' . $erreur->getMessage() . "\n");
}
fwrite(STDOUT, 'Base restaurée depuis ' . basename($chemin) . ' (' . $resultat['cousins'] . " cousins).\n");
fwrite(STDOUT, $resultat['lienGarde']
    ? "Le lien secret en service est gardé : personne n'est déconnecté.\n"
    : "La base remplacée n'avait pas de lien lisible : c'est le lien de la sauvegarde qui marche (sinon, php scripts/nouveau-lien.php).\n");
if ($resultat['remplacee'] !== null) {
    fwrite(STDOUT, 'L’ancienne base est gardée : stockage/sauvegardes/avant-restauration/' . basename($resultat['remplacee']) . "\n");
}
if ($resultat['migrations'] !== []) {
    fwrite(STDOUT, 'Migrations appliquées ensuite : ' . implode(', ', $resultat['migrations']) . "\n");
}
exit(0);
