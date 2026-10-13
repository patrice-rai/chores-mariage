<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\aujourdhui_paris;
use function Chores\checklist;
use function Chores\constats;
use function Chores\controler_video;
use function Chores\cousins_actifs;
use function Chores\effacer_fichier_video;
use function Chores\espace_suffisant;
use function Chores\espace_videos;
use function Chores\journaliser;
use function Chores\ligne_checklist;
use function Chores\maintenant_utc;
use function Chores\page_introuvable;
use function Chores\ranger_video;
use function Chores\repetition_par_id;
use function Chores\repetitions;
use function Chores\reponses_repetition;
use function Chores\url;

use const Chores\STATUTS_REPETITION;

// Administration du groupe (phase 5, spec § 2) : répétitions (date, heure, lieu, statut, « on travaille », compte
// rendu, présents constatés distincts des réponses), check-list du Jour J, vidéos (taille, codec, suppression,
// remplacement par une version convertie). 404 sans le cookie d'administration (niveau de route « admin »).

const CHAMPS_REPETITION = ['date' => 10, 'heure' => 60, 'lieu' => 120, 'travail' => 200, 'note' => 300, 'compte_rendu' => 2000];
const CONSTATS = ['' => 'pas noté', '1' => 'présent', '0' => 'absent'];

// --- Répétitions -------------------------------------------------------------------------------------------

/**
 * Valide les champs d'une répétition. @return array{erreurs: array<string, string>, valeurs: array<string, string>}
 */
function valider_repetition(Contexte $c, ?int $id): array
{
    $valeurs = ['statut' => (string) $c->champ('statut')];
    $erreurs = [];
    foreach (CHAMPS_REPETITION as $champ => $max) {
        $valeurs[$champ] = texte($c, $champ);
        if (mb_strlen($valeurs[$champ]) > $max) {
            $erreurs['repetition-' . $champ] = "Trop long : {$max} caractères au plus.";
        }
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeurs['date']);
    if ($date === false || $date->format('Y-m-d') !== $valeurs['date']) {
        $erreurs['repetition-date'] = 'Date illisible : écris-la AAAA-MM-JJ, par exemple 2099-03-07.';
    } else {
        $existe = $c->pdo()->prepare('SELECT 1 FROM repetitions WHERE date = ? AND id <> ?');
        $existe->execute([$valeurs['date'], $id ?? 0]);
        if ($existe->fetchColumn() !== false) {
            $erreurs['repetition-date'] = 'Une répétition existe déjà ce jour-là : modifie-la plutôt.';
        }
    }
    if (!array_key_exists($valeurs['statut'], STATUTS_REPETITION)) {
        $erreurs['repetition-statut'] = 'Choisis un statut : proposée, décidée, passée ou annulée.';
    }

    return ['erreurs' => $erreurs, 'valeurs' => $valeurs];
}

/** GET /admin/repetitions */
function admin_repetitions(Contexte $c, array $params, array $erreurs = [], array $saisie = [], int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $lignes = array_map(static fn (array $r): array => [
        'repetition' => $r,
        'reponses' => reponses_repetition($pdo, (int) $r['id']),
    ], array_reverse(repetitions($pdo)));

    return vue_admin($c, 'repetitions', 'Répétitions', [
        'lignes' => $lignes,
        'aujourdhui' => aujourdhui_paris(),
        'erreurs' => $erreurs,
        'saisie' => $saisie + ['date' => '', 'heure' => '', 'lieu' => '', 'statut' => 'proposee', 'travail' => '', 'note' => ''],
        'confirmation' => $c->parametre('ok') === 'ajoutee' ? 'Répétition ajoutée.' : null,
    ], $statut);
}

/** POST /admin/repetitions : ajoute une répétition (source « site »). */
function admin_ajouter_repetition(Contexte $c, array $params): Reponse
{
    $validation = valider_repetition($c, null);
    unset($validation['erreurs']['repetition-compte_rendu']);
    if ($validation['erreurs'] !== []) {
        return admin_repetitions($c, $params, $validation['erreurs'], $validation['valeurs'], 422);
    }
    $v = $validation['valeurs'];
    $pdo = $c->pdo();
    $pdo->prepare("INSERT INTO repetitions (date, heure, lieu, statut, travail, note, source) VALUES (?, ?, ?, ?, ?, ?, 'site')")
        ->execute([$v['date'], nul($v['heure']), nul($v['lieu']), $v['statut'], nul($v['travail']), nul($v['note'])]);
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'répétition ajoutée : ' . $v['date']);

    return Reponse::redirection(url($c->config, '/admin/repetitions?ok=ajoutee'), 303);
}

function nul(string $texte): ?string
{
    return $texte === '' ? null : $texte;
}

/** GET /admin/repetitions/{id} */
function admin_repetition(Contexte $c, array $params, array $erreurs = [], ?array $saisie = null, int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $r = repetition_par_id($pdo, $params['id'] ?? '');
    if ($r === null) {
        return page_introuvable($c);
    }
    $constats = constats($pdo, (int) $r['id']);
    $reponses = [];
    $requete = $pdo->prepare('SELECT cousin_id, reponse, source FROM presences WHERE repetition_id = ?');
    $requete->execute([(int) $r['id']]);
    foreach ($requete->fetchAll() as $l) {
        $reponses[(int) $l['cousin_id']] = ['reponse' => (string) $l['reponse'], 'source' => (string) $l['source']];
    }
    $valeurs = $saisie ?? [
        'date' => (string) $r['date'], 'heure' => (string) ($r['heure'] ?? ''), 'lieu' => (string) ($r['lieu'] ?? ''),
        'statut' => (string) $r['statut'], 'travail' => (string) ($r['travail'] ?? ''), 'note' => (string) ($r['note'] ?? ''),
        'compte_rendu' => (string) ($r['compte_rendu'] ?? ''),
        'constats' => array_map('strval', $constats),
    ];

    return vue_admin($c, 'repetition', 'Répétition du ' . \Chores\date_en_mots((string) $r['date'], ''), [
        'barreRetour' => url($c->config, '/admin/repetitions'),
        'repetition' => $r,
        'cousins' => cousins_actifs($pdo),
        'reponses' => $reponses,
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'confirmation' => $c->parametre('ok') === '1' ? 'Répétition enregistrée.' : null,
    ], $statut);
}

/** POST /admin/repetitions/{id} : champs, compte rendu et constats (« pas noté », « présent », « absent »). */
function admin_enregistrer_repetition(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $r = repetition_par_id($pdo, $params['id'] ?? '');
    if ($r === null) {
        return page_introuvable($c);
    }
    $validation = valider_repetition($c, (int) $r['id']);
    $erreurs = $validation['erreurs'];
    $v = $validation['valeurs'];
    $choix = is_array($c->post['constat'] ?? null) ? $c->post['constat'] : [];
    $v['constats'] = [];
    foreach (cousins_actifs($pdo) as $cousin) {
        $valeur = $choix[(string) $cousin['id']] ?? '';
        if (!is_string($valeur) || !array_key_exists($valeur, CONSTATS)) {
            $erreurs['constat-' . $cousin['id']] = $cousin['prenom'] . ' : choisis « pas noté », « présent » ou « absent ».';
            continue;
        }
        if ($valeur !== '') {
            $v['constats'][$cousin['id']] = $valeur;
        }
    }
    if ($erreurs !== []) {
        return admin_repetition($c, $params, $erreurs, $v, 422);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE repetitions SET date = ?, heure = ?, lieu = ?, statut = ?, travail = ?, note = ?, compte_rendu = ? WHERE id = ?')
            ->execute([$v['date'], nul($v['heure']), nul($v['lieu']), $v['statut'], nul($v['travail']), nul($v['note']), nul($v['compte_rendu']), (int) $r['id']]);
        // Seuls les cousins affichés dans le formulaire (actifs) sont réécrits.
        $pdo->prepare('DELETE FROM constats WHERE repetition_id = ? AND cousin_id IN (SELECT id FROM cousins WHERE actif = 1)')->execute([(int) $r['id']]);
        $ajout = $pdo->prepare('INSERT INTO constats (repetition_id, cousin_id, present) VALUES (?, ?, ?)');
        foreach ($v['constats'] as $cousinId => $present) {
            $ajout->execute([(int) $r['id'], (int) $cousinId, (int) $present]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'répétition du ' . $v['date'] . ' (' . STATUTS_REPETITION[$v['statut']] . ')');

    return Reponse::redirection(url($c->config, '/admin/repetitions/' . (int) $r['id'] . '?ok=1'), 303);
}

// --- Check-list du Jour J ----------------------------------------------------------------------------------

/** GET /admin/jour-j */
function admin_jour_j(Contexte $c, array $params, array $erreurs = [], array $saisie = [], int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $aSupprimer = ligne_checklist($pdo, (string) $c->parametre('supprimer'));
    $confirmations = ['ajoutee' => 'Ligne ajoutée.', 'renommee' => 'Ligne renommée.', 'deplacee' => 'Ordre enregistré.', 'supprimee' => 'Ligne supprimée.'];

    return vue_admin($c, 'jour-j', 'Jour J', [
        'lignes' => checklist($pdo),
        'ordre' => \Chores\ordre_de_passage($pdo),
        'aSupprimer' => $aSupprimer,
        'erreurs' => $erreurs,
        'saisie' => $saisie,
        'confirmation' => $confirmations[(string) $c->parametre('ok')] ?? null,
    ], $statut);
}

/** POST /admin/jour-j : ajouter, renommer, monter, descendre, supprimer une ligne de la check-list. */
function admin_modifier_jour_j(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $action = (string) $c->champ('action');
    $retour = static fn (string $ok): Reponse => Reponse::redirection(url($c->config, '/admin/jour-j?ok=' . $ok), 303);
    if ($action === 'ajouter') {
        $libelle = texte($c, 'libelle');
        if ($libelle === '' || mb_strlen($libelle) > 80) {
            return admin_jour_j($c, $params, ['nouvelle-ligne' => 'Nouvelle ligne : écris-la, de 1 à 80 caractères.'], ['nouvelle' => $libelle], 422);
        }
        $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), 0) + 1 FROM checklist')->fetchColumn();
        $pdo->prepare('INSERT INTO checklist (libelle, ordre) VALUES (?, ?)')->execute([$libelle, $ordre]);
        journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'check-list : ajout de « ' . $libelle . ' »');

        return $retour('ajoutee');
    }
    $ligne = ligne_checklist($pdo, (string) $c->champ('id'));
    if ($ligne === null) {
        return page_introuvable($c);
    }
    $id = (int) $ligne['id'];
    switch ($action) {
        case 'renommer':
            $libelle = texte($c, 'libelle');
            if ($libelle === '' || mb_strlen($libelle) > 80) {
                return admin_jour_j($c, $params, ['ligne-' . $id => 'Ligne « ' . $ligne['libelle'] . ' » : écris-la, de 1 à 80 caractères.'], ['ligne-' . $id => $libelle], 422);
            }
            $pdo->prepare('UPDATE checklist SET libelle = ? WHERE id = ?')->execute([$libelle, $id]);
            journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'check-list : « ' . $ligne['libelle'] . ' » devient « ' . $libelle . ' »');

            return $retour('renommee');
        case 'monter':
        case 'descendre':
            $ids = array_column(checklist($pdo), 'id');
            $i = array_search($id, $ids, true);
            $j = $action === 'monter' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                $maj = $pdo->prepare('UPDATE checklist SET ordre = ? WHERE id = ?');
                foreach ($ids as $rang => $autre) {
                    $maj->execute([$rang + 1, $autre]);
                }
            }

            return $retour('deplacee');
        case 'supprimer':
            $pdo->prepare('DELETE FROM checklist WHERE id = ?')->execute([$id]);
            journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'check-list : « ' . $ligne['libelle'] . ' » supprimée');

            return $retour('supprimee');
        default:
            return page_introuvable($c);
    }
}

// --- Vidéos ------------------------------------------------------------------------------------------------

/** @return list<array<string, mixed>> toutes les vidéos non supprimées (cousins et références), les plus récentes d'abord */
function toutes_les_videos(\PDO $pdo, ?int $id = null): array
{
    $requete = $pdo->prepare(
        'SELECT v.*, c.prenom AS auteur, ch.titre AS chanson_titre, ch.slug AS chanson_slug FROM videos v
         JOIN chansons ch ON ch.id = v.chanson_id LEFT JOIN cousins c ON c.id = v.cousin_id
         WHERE v.supprime_le IS NULL' . ($id !== null ? ' AND v.id = ?' : '') . ' ORDER BY v.cree_le DESC, v.id DESC'
    );
    $requete->execute($id !== null ? [$id] : []);

    return $requete->fetchAll();
}

/** @return array<string, mixed>|null */
function video_admin(\PDO $pdo, string $id): ?array
{
    return preg_match('/^[1-9][0-9]{0,8}$/D', $id) ? (toutes_les_videos($pdo, (int) $id)[0] ?? null) : null;
}

/** GET /admin/videos */
function admin_videos(Contexte $c, array $params, ?int $erreurPour = null, ?string $erreur = null, int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $confirmations = ['supprimee' => 'Vidéo supprimée : le fichier est effacé du serveur.', 'remplacee' => 'Vidéo remplacée par la version convertie.'];

    return vue_admin($c, 'videos', 'Vidéos', [
        'videos' => toutes_les_videos($pdo),
        'aSupprimer' => video_admin($pdo, (string) $c->parametre('supprimer')),
        'espace' => espace_videos($pdo),
        'espaceMax' => (int) $c->config['ESPACE_MAX_MO'] * 1024 * 1024,
        'maxMo' => (int) $c->config['TAILLE_MAX_UPLOAD_MO'],
        'erreurPour' => $erreurPour,
        'erreur' => $erreur,
        'confirmation' => $confirmations[(string) $c->parametre('ok')] ?? null,
    ], $statut);
}

/** POST /admin/videos/{id}/supprimer : vidéo d'un cousin ou de référence ; le fichier est effacé. */
function admin_supprimer_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_admin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return page_introuvable($c);
    }
    $pdo->beginTransaction();
    try {
        $fichier = \Chores\marquer_video_supprimee($pdo, (int) $video['id']);
        $pdo->prepare('UPDATE chansons SET video_ref_id = NULL WHERE video_ref_id = ?')->execute([(int) $video['id']]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    if ($fichier !== null) {
        effacer_fichier_video($fichier);
    }
    journaliser($pdo, 'video_suppr', $c->cousin['id'] ?? null, 'administration : ' . $video['chanson_titre'] . ' : ' . $video['titre']);

    return Reponse::redirection(url($c->config, '/admin/videos?ok=supprimee'), 303);
}

/**
 * POST /admin/videos/{id}/remplacer : version convertie sur le PC de Patrice (MP4 H.264 ou WebM ; HEVC refusé).
 * Même ligne (page, commentaires et durée gardés), nouveau nom interne (l'adresse change : aucun cache ne sert
 * l'ancienne), ancien fichier effacé.
 */
function admin_remplacer_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_admin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return page_introuvable($c);
    }
    $fichier = isset($c->fichiers['video']) && is_array($c->fichiers['video']) ? $c->fichiers['video'] : null;
    $controle = controler_video($fichier, (int) $c->config['TAILLE_MAX_UPLOAD_MO'] * 1024 * 1024, ['video/mp4', 'video/webm']);
    $erreur = $controle['erreur'];
    if ($erreur === null && $controle['codec'] === 'hevc') {
        $erreur = 'Cette version est encore en HEVC : convertis-la en H.264 avec la commande de docs/VIDEOS.md.';
    }
    if ($erreur === null && !espace_suffisant($pdo, $c->config, (int) $controle['taille'] - (int) $video['taille_o'])) {
        $erreur = 'Plus de place : supprime d’abord d’autres vidéos.';
    }
    if ($erreur !== null) {
        // Fichier refusé, jamais déplacé : PHP efface lui-même le fichier temporaire.
        return admin_videos($c, $params, (int) $video['id'], $erreur, 422);
    }
    $nom = ranger_video($c, (string) $controle['chemin'], (string) $controle['extension']);
    try {
        $maj = $pdo->prepare('UPDATE videos SET fichier = ?, mime = ?, taille_o = ?, codec_devine = ? WHERE id = ? AND supprime_le IS NULL');
        $maj->execute([$nom, $controle['mime'], $controle['taille'], $controle['codec'], (int) $video['id']]);
    } catch (\Throwable $e) {
        effacer_fichier_video($nom);
        throw $e;
    }
    if ($maj->rowCount() === 0) {
        // Supprimée entre-temps (par son auteur) : rien à remplacer, le nouveau fichier ne reste pas orphelin.
        effacer_fichier_video($nom);

        return page_introuvable($c);
    }
    effacer_fichier_video((string) $video['fichier']);
    journaliser($pdo, 'video_ajout', $c->cousin['id'] ?? null, 'version convertie : ' . $video['chanson_titre'] . ' : ' . $video['titre'] . ' (' . $controle['codec'] . ')');

    return Reponse::redirection(url($c->config, '/admin/videos?ok=remplacee#video-' . (int) $video['id']), 303);
}
