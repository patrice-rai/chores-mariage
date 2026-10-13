<?php

declare(strict_types=1);

// Importe les faits du groupe (spec § 5) : docs/whatsapp/etat.json et docs/distribution.md (donnees/ dans le
// paquet). Idempotent : relancé,
// il n'ajoute que ce qui manque et ne réécrit jamais ce que Patrice a modifié dans l'administration.
// Usage (dans le conteneur ou en SSH) : php scripts/importer-etat.php [etat.json] [distribution.md]

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

Chores\prendre_identite_du_stockage();

if (Chores\en_maintenance()) {
    fwrite(STDERR, "Le site est en maintenance (restauration en cours) : réessaie dans une minute.\n");
    exit(1);
}

// Par défaut : docs/ dans le dépôt, donnees/ dans le paquet de mise en ligne.
$faits = Chores\fichiers_des_faits() ?? [__DIR__ . '/../donnees/etat.json', __DIR__ . '/../donnees/distribution.md'];
$etat = $argv[1] ?? $faits[0];
$distribution = $argv[2] ?? $faits[1];
foreach ([$etat, $distribution] as $fichier) {
    if (!is_file($fichier) || !is_readable($fichier)) {
        fwrite(STDERR, "Fichier introuvable : {$fichier}\n");
        exit(1);
    }
}

try {
    $pdo = Chores\connexion();
    Chores\migrer($pdo);
    $compte = Chores\importer_etat($pdo, (string) file_get_contents($etat), (string) file_get_contents($distribution));
    Chores\journaliser($pdo, 'admin_modif', null, 'import des faits du groupe');
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Import refusé (rien n’a été gardé) : ' . $erreur->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Import fait. Ajouts : %d chanson(s), %d lien(s), %d répétition(s), %d réponse(s), %d ligne(s) de check-list ; %d cousin(s) mis à jour.\n",
    $compte['chansons'],
    $compte['liens'],
    $compte['repetitions'],
    $compte['presences'],
    $compte['checklist'],
    $compte['cousins']
));
exit(0);
