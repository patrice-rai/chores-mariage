<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\demo_chanson;
use function Chores\demo_placements;
use function Chores\demo_sections;
use function Chores\demo_video_chemin;
use function Chores\lire_jetons;
use function Chores\mesurer_contrastes;
use function Chores\page_introuvable;
use function Chores\rendre;
use function Chores\servir_fichier;
use function Chores\url;

use const Chores\COOKIE_THEME;
use const Chores\DUREE_COOKIE;
use const Chores\THEMES;

// Phase 3 : bascule de thème, page /gabarits, vidéo de démonstration, mode répétition sur la chanson « Démo ».

/** POST /theme : enregistre le thème choisi et revient à la page (ou 204 pour la bascule en JavaScript). */
function changer_theme(Contexte $c, array $params): Reponse
{
    $theme = $c->champ('theme');
    if (!in_array($theme, THEMES, true)) {
        $theme = null;
    }
    $reponse = str_contains((string) $c->entete('accept'), 'application/json')
        ? new Reponse(204)
        : Reponse::redirection(url($c->config, chemin_de_retour($c->champ('retour'))), 303);

    return $theme === null ? $reponse->avecCookie(COOKIE_THEME, '', -1) : $reponse->avecCookie(COOKIE_THEME, $theme, DUREE_COOKIE);
}

/**
 * Chemin interne sûr pour revenir après un formulaire (sans BASE_PATH) : « / » suivi de lettres, chiffres,
 * « / - _ . » et d'éventuels paramètres simples. Tout le reste (« //hote », « \ », « : ») ramène à l'accueil.
 */
function chemin_de_retour(?string $retour): string
{
    if ($retour === null || !preg_match('#^/(?![/\\\\])[A-Za-z0-9/_.\-]*(\?[A-Za-z0-9=&_.\-%+]*)?$#D', $retour) || str_contains($retour, '..')) {
        return '/';
    }

    return $retour;
}

/** Numéro de section demandé (?section=N, à partir de 1) ramené à un index valable. */
function index_de_section(Contexte $c, int $nombre): int
{
    $n = $c->parametre('section');
    $i = $n !== null && preg_match('/^[1-9][0-9]{0,2}$/', $n) ? (int) $n - 1 : 0;

    return $nombre === 0 ? 0 : min($i, $nombre - 1);
}

/** GET /gabarits */
function gabarits(Contexte $c, array $params): Reponse
{
    $jetons = lire_jetons((string) file_get_contents(\Chores\dossier_public() . '/css/jetons.css'));
    $sections = demo_sections();

    return rendre($c, 'gabarits', [
        'titre' => 'Gabarits',
        'barre' => 'Gabarits',
        'barreAction' => $c->cousin !== null ? 'moi' : null,
        'feuilles' => ['/css/gabarits.css'],
        'scripts' => ['/js/lecteur.js'],
        'jetons' => $jetons,
        'contrastes' => mesurer_contrastes($jetons),
        'chanson' => demo_chanson(),
        'sections' => $sections,
        'courante' => index_de_section($c, count($sections)),
        'video' => is_file(demo_video_chemin()) ? url($c->config, '/gabarits/demo.webm') : null,
        'placements' => demo_placements(),
        'moi' => $c->cousin['prenom'] ?? 'Patrice',
        'essai' => $c->parametre('essai') === '1',
        'surligner' => $c->parametre('surligner') !== '0',
    ]);
}

/** POST /gabarits/essai : formulaires de démonstration ; rien n'est enregistré. */
function essai_gabarits(Contexte $c, array $params): Reponse
{
    return Reponse::redirection(url($c->config, '/gabarits?essai=1#essai'), 303);
}

/** GET /gabarits/demo.webm : vidéo synthétique de démonstration, avec plages d'octets. */
function video_demo(Contexte $c, array $params): Reponse
{
    return servir_fichier($c, demo_video_chemin(), 'video/webm');
}

/** GET /gabarits/repeter?section=N : mode répétition sur la chanson « Démo » (les vraies : cousins.php). */
function repeter(Contexte $c, array $params): Reponse
{
    $sections = demo_sections();
    $courante = index_de_section($c, count($sections));

    return rendre($c, 'repeter', [
        'titre' => 'Répéter Démo',
        'themeForce' => 'sombre',
        'classePage' => 'page-repetition',
        'pied' => false,
        'scripts' => ['/js/lecteur.js'],
        'chanson' => demo_chanson(),
        'sections' => $sections,
        'courante' => $courante,
        'video' => is_file(demo_video_chemin()) ? url($c->config, '/gabarits/demo.webm') : null,
        'hrefQuitter' => url($c->config, '/gabarits?section=' . ($courante + 1) . '#lecteur-demo'),
        'hrefSection' => url($c->config, '/gabarits/repeter?section='),
    ]);
}
