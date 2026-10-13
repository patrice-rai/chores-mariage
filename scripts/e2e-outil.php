<?php

declare(strict_types=1);

// Outil des tests de bout en bout, refusé hors ENV = dev. Les tests travaillent sur une base séparée
// (stockage/test.sqlite), servie par le site seulement pendant que le témoin stockage/e2e.actif existe : les
// données de Patrice ne sont jamais touchées, et tout est effacé à la fin. Sa sortie (JSON) est lue par les tests
// et n'est jamais affichée.
//   php scripts/e2e-outil.php debut    : pose le témoin, part d'une base de test vide ;
//   php scripts/e2e-outil.php preparer : recrée la base de test (migrations + import des faits), remet le débit à
//                                        zéro, crée un lien ; renvoie {"lien": "/e/…", "cle": "/admin/cle/…"} ;
//   php scripts/e2e-outil.php vierge   : base de test vide (écran d'installation) ; renvoie {"cle": "…"} ;
//   php scripts/e2e-outil.php inspecter: cherche une adresse IP ou un navigateur dans le journal et le débit ;
//   php scripts/e2e-outil.php fin      : efface la base de test, ses vidéos et le témoin.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

Chores\prendre_identite_du_stockage();

$config = Chores\charger_configuration();
if ($config['ENV'] !== 'dev') {
    fwrite(STDERR, "Outil de test refusé hors développement.\n");
    exit(1);
}

/** Efface la base de test et les vidéos qu'elle référence (jamais celles de la vraie base). */
function effacer_base_de_test(): void
{
    $chemin = Chores\chemin_base_de_test();
    if (is_file($chemin)) {
        try {
            $pdo = Chores\connexion($chemin);
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'videos'")->fetchColumn();
            if ($tables !== false) {
                foreach ($pdo->query('SELECT fichier FROM videos')->fetchAll(PDO::FETCH_COLUMN) as $fichier) {
                    Chores\effacer_fichier_video((string) $fichier);
                }
            }
            $pdo = null;
        } catch (Throwable $erreur) {
            fwrite(STDERR, 'Base de test illisible, effacée quand même : ' . $erreur->getMessage() . "\n");
        }
    }
    foreach (['', '-wal', '-shm'] as $suffixe) {
        if (is_file($chemin . $suffixe)) {
            unlink($chemin . $suffixe);
        }
    }
    // Sauvegardes de la base de test (« Faire une sauvegarde maintenant » pendant les tests), jamais celles de Patrice.
    $dossier = Chores\dossier_sauvegardes($chemin);
    foreach ([...(glob($dossier . '/test-*.sqlite') ?: []), ...(glob($dossier . '/avant-restauration/test-*.sqlite') ?: [])] as $sauvegarde) {
        unlink($sauvegarde);
    }
}

$commande = $argv[1] ?? '';
$temoin = Chores\RACINE . Chores\TEMOIN_E2E;
switch ($commande) {
    case 'debut':
        effacer_base_de_test();
        file_put_contents($temoin, "Tests de bout en bout en cours : le site sert stockage/test.sqlite.\n");
        fwrite(STDOUT, "{}\n");
        exit(0);
    case 'fin':
        effacer_base_de_test();
        if (is_file($temoin)) {
            unlink($temoin);
        }
        fwrite(STDOUT, "{}\n");
        exit(0);
    case 'preparer':
        if (!is_file($temoin)) {
            file_put_contents($temoin, "Tests de bout en bout en cours : le site sert stockage/test.sqlite.\n");
        }
        effacer_base_de_test();
        $pdo = Chores\connexion(Chores\chemin_base_de_test());
        Chores\migrer($pdo);
        Chores\importer_etat(
            $pdo,
            (string) file_get_contents(Chores\RACINE . '/docs/whatsapp/etat.json'),
            (string) file_get_contents(Chores\RACINE . '/docs/distribution.md')
        );
        $jeton = Chores\nouveau_jeton($pdo);
        fwrite(STDOUT, json_encode([
            'lien' => Chores\url($config, '/e/' . $jeton),
            'cle' => Chores\url($config, '/admin/cle/' . $config['CLE_ADMIN']),
        ], JSON_UNESCAPED_SLASHES) . "\n");
        exit(0);
    case 'vierge':
        // Base de test vide (aucune table, aucun lien) : écran d'installation sans SSH.
        if (!is_file($temoin)) {
            file_put_contents($temoin, "Tests de bout en bout en cours : le site sert stockage/test.sqlite.\n");
        }
        effacer_base_de_test();
        Chores\connexion(Chores\chemin_base_de_test());
        fwrite(STDOUT, json_encode(['cle' => $config['CLE_ADMIN']], JSON_UNESCAPED_SLASHES) . "\n");
        exit(0);
    case 'inspecter':
        // Recette (spec § 11) : le journal et la table de débit de la base de test ne contiennent aucune adresse
        // IP, aucun navigateur. Renvoie le nombre de lignes lues et celles qui en contiendraient.
        $pdo = Chores\connexion(Chores\chemin_base_de_test());
        $lignes = 0;
        $suspectes = [];
        foreach (['journal', 'debit'] as $table) {
            foreach ($pdo->query("SELECT * FROM {$table}")->fetchAll() as $ligne) {
                ++$lignes;
                $texte = implode(' | ', array_map('strval', $ligne));
                $sansDates = (string) preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', '', $texte);
                if (preg_match('/\b\d{1,3}(\.\d{1,3}){3}\b|\b[0-9a-f]{1,4}(:[0-9a-f]{0,4}){2,7}\b|Mozilla|Chrome\/|Safari\//i', $sansDates) === 1) {
                    $suspectes[] = $table;
                }
            }
        }
        fwrite(STDOUT, json_encode(['lignes' => $lignes, 'suspectes' => $suspectes]) . "\n");
        exit(0);
    default:
        fwrite(STDERR, "Usage : php scripts/e2e-outil.php debut|preparer|vierge|inspecter|fin\n");
        exit(1);
}
