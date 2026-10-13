<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\rendre;
use function Chores\url;

// PWA (phase 5, spec § 8) : service worker servi par PHP (sa version et la liste exacte de l'interface à garder
// suivent les fichiers : rien à tenir à la main), page « Hors ligne » publique (précachée, sans donnée).

/** Interface gardée à l'installation du service worker (adresses sans BASE_PATH ; les modules en ajoutent d'autres). */
const PRECACHE = [
    '/css/jetons.css', '/css/base.css', '/css/composants.css', '/css/pages.css',
    '/js/site.js', '/js/pwa.js', '/js/file-niveaux.js', '/js/lecteur.js', '/js/lecteur-calc.js', '/js/decompte.js',
    '/js/chanson.js', '/js/video.js', '/js/televersement.js',
    '/polices/yeseva-one-latin-400-normal-v5-3-0.woff2', '/polices/yeseva-one-latin-ext-400-normal-v5-3-0.woff2',
    '/polices/nunito-sans-latin-wght-normal-v5-3-0.woff2', '/polices/nunito-sans-latin-ext-wght-normal-v5-3-0.woff2',
    '/icones/icone-192.png', '/icones/icone-512.png', '/icones/apple-touch-icon.png', '/manifest.webmanifest',
];

/** Modèle du service worker (app/sw.js, hors racine web : seule la version servie par PHP existe). */
function modele_service_worker(): string
{
    return (string) file_get_contents(__DIR__ . '/../sw.js');
}

/**
 * Version : empreinte du modèle et de la taille et la date de tous les fichiers statiques que le service worker peut garder (css, js, polices,
 * icônes, manifeste : aussi ceux qui ne sont gardés qu'à la première visite, comme admin.js) et de toutes les vues
 * (la page « Hors ligne » précachée est rendue par PHP). Un fichier changé → nouveau service worker.
 */
function version_service_worker(): string
{
    $public = \Chores\dossier_public();
    $fichiers = [$public . '/manifest.webmanifest'];
    foreach (['/css', '/js', '/polices', '/icones'] as $dossier) {
        $fichiers = array_merge($fichiers, glob($public . $dossier . '/*') ?: []);
    }
    $vues = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../vues', \FilesystemIterator::SKIP_DOTS));
    foreach ($vues as $vue) {
        if ($vue instanceof \SplFileInfo && $vue->isFile()) {
            $fichiers[] = $vue->getPathname();
        }
    }
    sort($fichiers);
    $contexte = hash_init('sha256');
    hash_update($contexte, modele_service_worker());
    // Taille et date de chaque fichier (pas son contenu) : /sw.js est public et redemandé à chaque visite, il doit
    // rester léger. Un fichier changé, déployé ou décompressé change de date.
    foreach ($fichiers as $fichier) {
        if (is_file($fichier)) {
            hash_update($contexte, $fichier . "\0" . filesize($fichier) . "\0" . filemtime($fichier) . "\n");
        }
    }

    return substr(hash_final($contexte), 0, 12);
}

/** GET /sw.js : le service worker, avec sa version, son périmètre et la liste de l'interface. */
function service_worker(Contexte $c, array $params): Reponse
{
    $base = url($c->config, '/');
    $precache = array_merge(array_map(static fn (string $chemin): string => url($c->config, $chemin), PRECACHE), [url($c->config, '/hors-ligne')]);
    $code = strtr(modele_service_worker(), [
        "'__VERSION__'" => json_encode(version_service_worker()),
        "'__BASE__'" => json_encode($base, JSON_UNESCAPED_SLASHES),
        "['__PRECACHE__']" => (string) json_encode($precache, JSON_UNESCAPED_SLASHES),
    ]);

    return new Reponse(200, $code, [
        'Content-Type' => 'text/javascript; charset=utf-8',
        // Toujours relu : une nouvelle version doit s'installer à la visite suivante.
        'Cache-Control' => 'no-cache',
    ]);
}

/** GET /hors-ligne : page de repli du service worker (aucune donnée : la liste des vidéos gardées est lue par JS). */
function page_hors_ligne(Contexte $c, array $params): Reponse
{
    return rendre($c, 'hors-ligne', [
        'titre' => 'Hors ligne',
        'barre' => 'Chorés du mariage',
        'barreAction' => null,
        'bascule' => false,
    ])->avecEntete('Cache-Control', 'no-cache');
}
