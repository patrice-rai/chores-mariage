<?php

declare(strict_types=1);

// Sauvegarde de la base (spec § 10) : copie cohérente dans stockage/sauvegardes/base-AAAA-MM-JJ-HHMMSS.sqlite,
// sans arrêter le site (VACUUM INTO). Avec --garder=N, seules les N plus récentes sont gardées (rien n'est effacé
// sinon). Les vidéos ne sont pas copiées : elles se téléchargent avec le dossier stockage/
// (docs/deploy/sauvegardes.md).
// Usage (en SSH, ou par une tâche planifiée de hPanel) : php scripts/sauvegarde.php [--garder=N]

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

Chores\prendre_identite_du_stockage();

if (Chores\en_maintenance()) {
    fwrite(STDERR, "Le site est en maintenance (restauration en cours) : réessaie dans une minute.\n");
    exit(1);
}

$garder = null;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--garder=([1-9][0-9]{0,2})$/D', $argument, $m) === 1) {
        $garder = (int) $m[1];
    } else {
        fwrite(STDERR, "Usage : php scripts/sauvegarde.php [--garder=N]\n");
        exit(1);
    }
}

try {
    $fichier = Chores\sauvegarder_base(Chores\chemin_base());
    $pdo = Chores\connexion();
    Chores\journaliser($pdo, 'admin_modif', null, 'sauvegarde ' . basename($fichier) . ' (script)');
    $effacees = 0;
    if ($garder !== null) {
        $effacees = Chores\elaguer_sauvegardes(dirname($fichier), Chores\prefixe_sauvegardes(Chores\chemin_base()), $garder);
    }
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Sauvegarde impossible : ' . $erreur->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, 'Sauvegarde faite : stockage/sauvegardes/' . basename($fichier) . ' (' . Chores\format_octets((int) filesize($fichier)) . ").\n");
if ($effacees > 0) {
    fwrite(STDOUT, "{$effacees} ancienne(s) sauvegarde(s) effacée(s).\n");
}
exit(0);
