<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\cousin_actif;
use function Chores\cousins_actifs;
use function Chores\debit_autorise;
use function Chores\jeton_valide;
use function Chores\journaliser;
use function Chores\lire_cookie_prenom;
use function Chores\rendre;
use function Chores\reponse_trop_d_essais;
use function Chores\url;
use function Chores\valeur_cookie_acces;
use function Chores\valeur_cookie_prenom;
use function Chores\version_lien;

use const Chores\COOKIE_ACCES;
use const Chores\COOKIE_PRENOM;
use const Chores\DUREE_COOKIE;

// Entrée par le lien secret, choix du prénom (Ma page : app/controleurs/cousins.php).

/** GET /e/{jeton} : bon jeton → cookie d'accès et retour à l'accueil (le jeton quitte la barre d'adresse). */
function entrer_par_le_lien(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    if (!debit_autorise($pdo, $c->config['SECRET'], 'entree', $c->ip)) {
        return reponse_trop_d_essais($c, 'entree');
    }
    $prenomId = lire_cookie_prenom($c->config['SECRET'], $c->cookie(COOKIE_PRENOM), version_lien($pdo));
    $cousinId = $prenomId !== null ? (cousin_actif($pdo, $prenomId)['id'] ?? null) : null;

    if (!jeton_valide($pdo, $params['jeton'] ?? '')) {
        journaliser($pdo, 'refus_jeton', $cousinId, 'lien');

        return Reponse::redirection(url($c->config, '/lien'), 303)->avecEntete('Cache-Control', 'no-store');
    }

    $version = version_lien($pdo);
    journaliser($pdo, 'entree', $cousinId, 'version ' . $version);

    return Reponse::redirection(url($c->config, '/'), 303)
        ->avecEntete('Cache-Control', 'no-store')
        ->avecCookie(COOKIE_ACCES, valeur_cookie_acces($c->config['SECRET'], $version), DUREE_COOKIE);
}

/** GET /lien : lien manquant ou périmé. */
function lien_manquant(Contexte $c, array $params): Reponse
{
    // Ce téléphone avait un cookie d'accès qui ne vaut plus (lien renouvelé) : il oublie ce qu'il gardait (pages et
    // vidéos du service worker, liste des vidéos gardées, niveaux en attente). Un simple lien vers /lien, lui, ne
    // vide rien.
    $perime = $c->cookie(\Chores\COOKIE_ACCES) !== null
        && !\Chores\cookie_acces_valide($c->config['SECRET'], \Chores\version_lien($c->pdo()), $c->cookie(\Chores\COOKIE_ACCES));
    $reponse = rendre($c, 'lien', ['titre' => 'Lien à demander', 'oublier' => $perime, 'perime' => $perime]);

    return $perime ? $reponse->avecEntete('X-Chores-Oublier', '1') : $reponse;
}

/** GET / : neuf prénoms ; si le prénom est déjà choisi, direction Ma page. */
function entree(Contexte $c, array $params): Reponse
{
    if ($c->cousin !== null) {
        return Reponse::redirection(url($c->config, '/moi'), 302);
    }

    return rendre($c, 'entree', ['titre' => 'Entrée', 'cousins' => cousins_actifs($c->pdo()), 'texteEntree' => \Chores\reglage($c->pdo(), 'texte_entree')]);
}

/** POST /prenom */
function choisir_prenom(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    if (!debit_autorise($pdo, $c->config['SECRET'], 'prenom', $c->ip)) {
        return reponse_trop_d_essais($c, 'prenom');
    }
    $id = $c->champ('cousin');
    $cousin = $id !== null && preg_match('/^[1-9][0-9]{0,8}$/', $id) ? cousin_actif($pdo, (int) $id) : null;
    if ($cousin === null) {
        return Reponse::redirection(url($c->config, '/'), 303);
    }
    journaliser($pdo, 'prenom', $cousin['id']);

    return Reponse::redirection(url($c->config, '/moi'), 303)
        ->avecCookie(COOKIE_PRENOM, valeur_cookie_prenom($c->config['SECRET'], $cousin['id'], version_lien($pdo)), DUREE_COOKIE);
}

/** POST /changer : on oublie le prénom, retour aux neuf boutons. */
function changer_prenom(Contexte $c, array $params): Reponse
{
    return Reponse::redirection(url($c->config, '/'), 303)->avecCookie(COOKIE_PRENOM, '', -1);
}
