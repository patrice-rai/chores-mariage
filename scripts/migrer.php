<?php

declare(strict_types=1);

// Applique les migrations manquantes (app/migrations/NNN-nom.sql), purge le journal de plus d'un an et les
// fenêtres de débit de plus de deux jours. Idempotent : relancé, il ne fait rien de plus.
// Usage (dans le conteneur) : php scripts/migrer.php

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
    $pdo = Chores\connexion();
    $appliquees = Chores\migrer($pdo);
    $purgeJournal = Chores\purger_journal($pdo);
    $purgeDebit = Chores\purger_debit($pdo);
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Migration impossible : ' . $erreur->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, $appliquees === []
    ? "Base à jour : aucune migration à appliquer.\n"
    : 'Migrations appliquées : ' . implode(', ', $appliquees) . "\n");
if ($purgeJournal > 0) {
    fwrite(STDOUT, "Journal : {$purgeJournal} ligne(s) de plus d'un an supprimée(s).\n");
}
if ($purgeDebit > 0) {
    fwrite(STDOUT, "Débit : {$purgeDebit} fenêtre(s) périmée(s) supprimée(s).\n");
}
exit(0);
