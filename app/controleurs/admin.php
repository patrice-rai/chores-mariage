<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\cle_admin_valide;
use function Chores\cousin_actif;
use function Chores\debit_autorise;
use function Chores\dernieres_lignes_journal;
use function Chores\journaliser;
use function Chores\lien_complet;
use function Chores\lire_cookie_prenom;
use function Chores\nouveau_jeton;
use function Chores\page_introuvable;
use function Chores\reglage;
use function Chores\rendre;
use function Chores\reponse_trop_d_essais;
use function Chores\url;
use function Chores\valeur_cookie_acces;
use function Chores\valeur_cookie_admin;
use function Chores\version_lien;

use const Chores\COOKIE_ACCES;
use const Chores\COOKIE_ADMIN;
use const Chores\COOKIE_PRENOM;
use const Chores\DUREE_COOKIE;

// Administration de Patrice (phase 2 : tableau, nouveau lien). 404 pour qui n'a pas le cookie d'administration.

/** GET /admin/cle/{cle} : bonne clé → cookie d'administration sur cet appareil ; sinon 404. */
function poser_cle_admin(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    if (!debit_autorise($pdo, $c->config['SECRET'], 'entree', $c->ip)) {
        return reponse_trop_d_essais($c, 'entree');
    }
    $prenomId = lire_cookie_prenom($c->config['SECRET'], $c->cookie(COOKIE_PRENOM), version_lien($pdo));
    $cousinId = $prenomId !== null ? (cousin_actif($pdo, $prenomId)['id'] ?? null) : null;

    if (!cle_admin_valide($c->config['CLE_ADMIN'], $params['cle'] ?? '')) {
        journaliser($pdo, 'refus_jeton', $cousinId, 'clé d’administration');

        return page_introuvable($c)->avecEntete('Cache-Control', 'no-store');
    }
    journaliser($pdo, 'admin_modif', $cousinId, 'administration ouverte sur un appareil');

    return Reponse::redirection(url($c->config, '/admin'), 303)
        ->avecEntete('Cache-Control', 'no-store')
        ->avecCookie(COOKIE_ADMIN, valeur_cookie_admin($c->config['SECRET'], $c->config['CLE_ADMIN']), DUREE_COOKIE);
}

/** GET /admin : version du lien, entrées par prénom, 50 dernières lignes du journal. */
function tableau_admin(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $entrees = (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'entree'")->fetchColumn();
    $parPrenom = $pdo->query(
        "SELECT c.prenom, COUNT(j.id) AS nombre FROM cousins c
         LEFT JOIN journal j ON j.cousin_id = c.id AND j.type = 'prenom'
         WHERE c.actif = 1 GROUP BY c.id ORDER BY c.ordre"
    )->fetchAll();

    $questions = json_decode((string) (reglage($pdo, 'questions_ouvertes') ?? '[]'), true);

    return rendre($c, 'admin/tableau', [
        'titre' => 'Administration',
        'barre' => 'Administration',
        'barreAction' => 'moi',
        'version' => version_lien($pdo),
        'creeLe' => reglage($pdo, 'jeton_cree_le'),
        'entrees' => $entrees,
        'parPrenom' => $parPrenom,
        'journal' => dernieres_lignes_journal($pdo, 20),
        'espaceStockage' => \Chores\taille_dossier(\Chores\RACINE . '/stockage'),
        'espaceVideos' => \Chores\espace_videos($pdo),
        'espaceMax' => $c->config['ESPACE_MAX_MO'] * 1024 * 1024,
        'questions' => is_array($questions) ? array_map('strval', $questions) : [],
        'importLe' => reglage($pdo, 'import_le'),
        'enAttente' => \Chores\migrations_en_attente($pdo),
        'derniereSauvegarde' => \Chores\liste_sauvegardes(
            \Chores\dossier_sauvegardes(\Chores\chemin_base_active($c->config)),
            \Chores\prefixe_sauvegardes(\Chores\chemin_base_active($c->config))
        )[0]['nom'] ?? null,
    ])->avecEntete('Cache-Control', 'no-store');
}

/** GET /admin/lien */
function formulaire_nouveau_lien(Contexte $c, array $params): Reponse
{
    return rendre($c, 'admin/lien', [
        'titre' => 'Nouveau lien',
        'barre' => 'Administration',
        'barreAction' => 'moi',
        'lien' => null,
        'version' => version_lien($c->pdo()),
        'horsGroupe' => [],
    ])->avecEntete('Cache-Control', 'no-store');
}

/** POST /admin/lien : nouveau jeton, l'ancien tombe aussitôt ; le lien n'est affiché qu'ici, une fois. */
function creer_nouveau_lien(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $attendue = $c->champ('version');
    $jeton = $attendue !== null && preg_match('/^[0-9]{1,9}$/', $attendue) === 1 ? nouveau_jeton($pdo, (int) $attendue) : null;
    if ($jeton === null) {
        // Formulaire renvoyé (retour arrière, rechargement) : on ne remplace pas le lien qui vient d'être diffusé.
        return rendre($c, 'admin/lien', [
            'titre' => 'Lien déjà renouvelé',
            'barre' => 'Administration',
            'barreAction' => 'moi',
            'lien' => null,
            'dejaRenouvele' => true,
            'version' => version_lien($pdo),
            'horsGroupe' => [],
        ], 409)->avecEntete('Cache-Control', 'no-store');
    }
    $version = version_lien($pdo);
    journaliser($pdo, 'admin_lien', $c->cousin['id'] ?? null, 'version ' . $version);
    $horsGroupe = $pdo->query('SELECT prenom FROM cousins WHERE actif = 1 AND dans_le_groupe_whatsapp = 0 ORDER BY ordre')
        ->fetchAll(\PDO::FETCH_COLUMN);

    return rendre($c, 'admin/lien', [
        'titre' => 'Nouveau lien créé',
        'barre' => 'Administration',
        'barreAction' => 'moi',
        'lien' => lien_complet($c->config, $jeton, $c->hote, $c->https),
        'version' => $version,
        'horsGroupe' => array_map('strval', $horsGroupe),
    ])->avecEntete('Cache-Control', 'no-store')
        // L'appareil de Patrice suit le nouveau lien : recharger cette page mène au refus 409, pas à une impasse.
        ->avecCookie(COOKIE_ACCES, valeur_cookie_acces($c->config['SECRET'], $version), DUREE_COOKIE);
}
