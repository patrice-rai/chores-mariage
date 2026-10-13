<?php

declare(strict_types=1);

namespace Chores;

// Chargement du noyau : configuration, base, réponses, sécurité, accès, routes. Aucun effet de bord ici :
// demarrer() traite la requête, les scripts et les tests n'utilisent que les fonctions.

const RACINE = __DIR__ . '/..';

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

require __DIR__ . '/version.php';
require __DIR__ . '/configuration.php';
require __DIR__ . '/bd.php';
require __DIR__ . '/http.php';
require __DIR__ . '/format.php';
require __DIR__ . '/jetons.php';
require __DIR__ . '/medias.php';
require __DIR__ . '/demo.php';
require __DIR__ . '/securite.php';
require __DIR__ . '/journal.php';
require __DIR__ . '/donnees.php';
require __DIR__ . '/groupe.php';
require __DIR__ . '/import.php';
require __DIR__ . '/televersement.php';
require __DIR__ . '/debit.php';
require __DIR__ . '/maintenance.php';
require __DIR__ . '/acces.php';
require __DIR__ . '/routeur.php';
require __DIR__ . '/controleurs/entree.php';
require __DIR__ . '/controleurs/admin.php';
require __DIR__ . '/controleurs/interface.php';
require __DIR__ . '/controleurs/cousins.php';
require __DIR__ . '/controleurs/admin-donnees.php';
require __DIR__ . '/controleurs/groupe.php';
require __DIR__ . '/controleurs/admin-groupe.php';
require __DIR__ . '/controleurs/pwa.php';
require __DIR__ . '/controleurs/installation.php';
require __DIR__ . '/controleurs/admin-base.php';
require __DIR__ . '/routes.php';

/** Traite la requête HTTP courante et envoie la réponse. */
function demarrer(): void
{
    // Jamais de compression des pages (elles portent le jeton CSRF : attaque BREACH) ; les fichiers statiques,
    // eux, sont compressés par le serveur (public/.htaccess).
    ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
    set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
        if (!(error_reporting() & $niveau)) {
            return false;
        }
        throw new \ErrorException($message, 0, $niveau, $fichier, $ligne);
    });

    try {
        $config = charger_configuration();
        if (en_maintenance(chemin_base_active($config))) {
            // Restauration en cours (app/maintenance.php) : la base n'est pas ouverte.
            $reponse = page_erreur($config, 503, 'Petite pause', 'Le site est en maintenance quelques instants. Réessaie dans une minute.')
                ->avecEntete('Retry-After', '60');
        } else {
            $contexte = Contexte::depuisGlobales($config);
            $reponse = traiter($contexte, routes());
            // Une vidéo peut partir pendant des minutes : la base est fermée avant (une connexion restée ouverte
            // pendant une restauration pourrait, en se fermant, effacer le journal de la base restaurée).
            if ($reponse->fichier !== null) {
                $contexte->fermerBase();
            }
        }
    } catch (\Throwable $erreur) {
        error_log('[chores] ' . $erreur::class . ' : ' . $erreur->getMessage() . ' (' . $erreur->getFile() . ':' . $erreur->getLine() . ')');
        $config ??= ['ENV' => 'production', 'BASE_PATH' => ''];
        $reponse = page_erreur(
            $config,
            500,
            'Oups, petit faux pas',
            'Quelque chose s’est mal passé de notre côté. Réessaie dans un instant ; si ça continue, dis-le à Patrice.'
        );
    }

    envoyer($reponse, $config);
}
