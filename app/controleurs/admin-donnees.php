<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\controler_video;
use function Chores\deplacer;
use function Chores\dernieres_lignes_journal;
use function Chores\distribution;
use function Chores\effacer_fichier_video;
use function Chores\espace_suffisant;
use function Chores\journaliser;
use function Chores\liens_de;
use function Chores\lien_sortant_valide;
use function Chores\lire_temps;
use function Chores\maintenant_utc;
use function Chores\page_introuvable;
use function Chores\placements_section;
use function Chores\poser_reglage;
use function Chores\ranger_video;
use function Chores\reglage;
use function Chores\rendre;
use function Chores\sections_de;
use function Chores\slug;
use function Chores\toutes_les_chansons;
use function Chores\url;
use function Chores\valider_section;

use const Chores\DIRECTIONS;
use const Chores\TYPES_LIENS;

// Administration de Patrice (phase 4, spec § 2) : réglages, chansons (fiche, danseurs, liens, vidéo de référence),
// sections, placements, journal. Écrans sobres faits des composants existants ; tout marche sans JavaScript
// (public/js/admin.js ajoute « Taper le tempo » et « Depuis le lecteur »). 404 sans le cookie d'administration.

const STATUTS_CHANSON = ['proposee' => 'proposée', 'decidee' => 'décidée', 'ecartee' => 'écartée'];
const REGLAGES_TEXTE = [
    'date_mariage' => 10, 'lieu_mariage' => 120, 'duree_danses_min' => 3, 'lien_sondage' => 300, 'texte_entree' => 200, 'contact_patrice' => 80,
];

/** Rend une page d'administration (barre « Administration », retour vers le tableau, jamais en cache). */
function vue_admin(Contexte $c, string $vue, string $titre, array $donnees, int $statut = 200): Reponse
{
    return rendre($c, 'admin/' . $vue, $donnees + [
        'titre' => $titre,
        'barre' => 'Administration',
        'barreAction' => 'retour',
        'barreRetour' => url($c->config, '/admin'),
    ], $statut)->avecEntete('Cache-Control', 'no-store');
}

/** Texte d'un champ, sans espaces autour ; '' si absent. */
function texte(Contexte $c, string $nom): string
{
    return trim((string) $c->champ($nom));
}

/** Entier borné, null si vide ; false si illisible ou hors bornes. */
function entier_facultatif(string $texte, int $min, int $max): int|null|false
{
    if ($texte === '') {
        return null;
    }
    if (!preg_match('/^\d{1,5}$/D', $texte) || (int) $texte < $min || (int) $texte > $max) {
        return false;
    }

    return (int) $texte;
}

function chanson_admin(Contexte $c, array $params): ?array
{
    return \Chores\chanson_par_slug($c->pdo(), $params['slug'] ?? '');
}

// --- Réglages ----------------------------------------------------------------------------------------------

/** GET /admin/reglages */
function admin_reglages(Contexte $c, array $params): Reponse
{
    $valeurs = [];
    foreach (array_keys(REGLAGES_TEXTE) as $cle) {
        $valeurs[$cle] = (string) (reglage($c->pdo(), $cle) ?? '');
    }
    $valeurs['placements_verrouilles'] = reglage($c->pdo(), 'placements_verrouilles') === '1';

    return vue_admin($c, 'reglages', 'Réglages', [
        'valeurs' => $valeurs,
        'erreurs' => [],
        'confirmation' => $c->parametre('ok') === '1' ? 'Réglages enregistrés.' : null,
    ]);
}

/** POST /admin/reglages */
function admin_enregistrer_reglages(Contexte $c, array $params): Reponse
{
    $valeurs = [];
    $erreurs = [];
    foreach (REGLAGES_TEXTE as $cle => $max) {
        $valeurs[$cle] = texte($c, $cle);
        if (mb_strlen($valeurs[$cle]) > $max) {
            $erreurs[$cle] = "Trop long : {$max} caractères au plus.";
        }
    }
    $valeurs['placements_verrouilles'] = $c->champ('placements_verrouilles') === '1';
    $date = $valeurs['date_mariage'];
    if ($date !== '' && (\DateTimeImmutable::createFromFormat('!Y-m-d', $date) === false || \DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') !== $date)) {
        $erreurs['date_mariage'] = 'Date illisible : choisis-la dans le calendrier ou écris-la AAAA-MM-JJ.';
    }
    if (entier_facultatif($valeurs['duree_danses_min'], 1, 240) === false) {
        $erreurs['duree_danses_min'] = 'Durée en minutes, de 1 à 240.';
    }
    if ($valeurs['lien_sondage'] !== '' && !lien_sortant_valide($valeurs['lien_sondage'])) {
        $erreurs['lien_sondage'] = 'Le lien doit commencer par https:// (par exemple un sondage Framadate).';
    }
    if ($erreurs !== []) {
        return vue_admin($c, 'reglages', 'Réglages', ['valeurs' => $valeurs, 'erreurs' => $erreurs, 'confirmation' => null], 422);
    }
    $pdo = $c->pdo();
    foreach (array_keys(REGLAGES_TEXTE) as $cle) {
        poser_reglage($pdo, $cle, $valeurs[$cle] === '' ? null : $valeurs[$cle]);
    }
    poser_reglage($pdo, 'placements_verrouilles', $valeurs['placements_verrouilles'] ? '1' : '0');
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'réglages');

    return Reponse::redirection(url($c->config, '/admin/reglages?ok=1'), 303);
}

// --- Chansons ----------------------------------------------------------------------------------------------

/** GET /admin/chansons */
function admin_chansons(Contexte $c, array $params, array $erreurs = [], string $titreSaisi = ''): Reponse
{
    $pdo = $c->pdo();
    $lignes = [];
    foreach (toutes_les_chansons($pdo) as $ch) {
        $lignes[] = [
            'chanson' => $ch,
            'danseurs' => count(distribution($pdo, (int) $ch['id'])),
            'sections' => count(sections_de($pdo, (int) $ch['id'])),
        ];
    }

    return vue_admin($c, 'chansons', 'Chansons', ['lignes' => $lignes, 'erreurs' => $erreurs, 'titreSaisi' => $titreSaisi], $erreurs === [] ? 200 : 422);
}

/** POST /admin/chansons : ajoute une chanson (titre seul, statut « proposée »). */
function admin_ajouter_chanson(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $titre = texte($c, 'titre');
    if ($titre === '' || mb_strlen($titre) > 80) {
        return admin_chansons($c, $params, ['titre' => 'Le titre est vide ou trop long : de 1 à 80 caractères.'], $titre);
    }
    $existe = $pdo->prepare('SELECT 1 FROM chansons WHERE titre = ? COLLATE NOCASE');
    $existe->execute([$titre]);
    if ($existe->fetchColumn() !== false) {
        return admin_chansons($c, $params, ['titre' => 'Cette chanson existe déjà.'], $titre);
    }
    $base = slug($titre);
    $slug = $base;
    for ($i = 2; \Chores\chanson_par_slug($pdo, $slug) !== null; ++$i) {
        $slug = substr($base, 0, 55) . '-' . $i;
    }
    $pdo->prepare("INSERT INTO chansons (slug, titre, statut) VALUES (?, ?, 'proposee')")->execute([$slug, $titre]);
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'chanson ajoutée : ' . $titre);

    return Reponse::redirection(url($c->config, '/admin/chansons/' . $slug . '?ok=ajoutee'), 303);
}

/** Valeurs du formulaire d'une chanson telles qu'en base (pour l'affichage). */
function valeurs_chanson(array $ch, array $distribution): array
{
    $danseurs = [];
    foreach ($distribution as $d) {
        $danseurs[$d['cousin_id']] = $d['statut'];
    }

    return [
        'titre' => (string) $ch['titre'],
        'film' => (string) ($ch['film'] ?? ''),
        'duree' => $ch['duree_s'] !== null ? \Chores\format_temps((int) $ch['duree_s']) : '',
        'bpm' => $ch['bpm'] !== null ? (string) $ch['bpm'] : '',
        'bpm_verifie' => (int) $ch['bpm_verifie'] === 1,
        'statut' => (string) $ch['statut'],
        'a_creer' => (int) $ch['a_creer'] === 1,
        'ordre_passage' => $ch['ordre_passage'] !== null ? (string) $ch['ordre_passage'] : '',
        'entree_qui' => (string) ($ch['entree_qui'] ?? ''),
        'entree_dou' => (string) ($ch['entree_dou'] ?? ''),
        'musique_qui' => (string) ($ch['musique_qui'] ?? ''),
        'duree_coupee' => $ch['duree_coupee_s'] !== null ? \Chores\format_temps((int) $ch['duree_coupee_s']) : '',
        'note' => (string) ($ch['note'] ?? ''),
        'danseurs' => $danseurs,
    ];
}

/** Page d'une chanson en administration (fiche, liens, vidéo de référence). */
function vue_chanson_admin(Contexte $c, array $ch, array $valeurs, array $erreurs, array $lienSaisi = [], array $erreursLien = [], ?string $erreurVideo = null, int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $reference = null;
    if ($ch['video_ref_id'] !== null) {
        $requete = $pdo->prepare('SELECT id, titre, mime, taille_o, codec_devine, cree_le FROM videos WHERE id = ? AND supprime_le IS NULL');
        $requete->execute([(int) $ch['video_ref_id']]);
        $reference = $requete->fetch() ?: null;
    }
    $confirmations = [
        'ajoutee' => 'Chanson ajoutée : complète sa fiche.',
        'fiche' => 'Fiche enregistrée.',
        'lien' => 'Lien ajouté.',
        'lien-supprime' => 'Lien supprimé.',
        'reference' => 'Vidéo de référence déposée : elle se lit sur la page de la chanson.',
        'retiree' => 'Vidéo de référence retirée.',
    ];

    return vue_admin($c, 'chanson', $ch['titre'] . ' (administration)', [
        'chanson' => $ch,
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'cousins' => \Chores\cousins_actifs($pdo),
        'liens' => liens_de($pdo, (int) $ch['id']),
        'lienSaisi' => $lienSaisi,
        'erreursLien' => $erreursLien,
        'reference' => $reference,
        'erreurVideo' => $erreurVideo,
        'confirmation' => $confirmations[(string) $c->parametre('ok')] ?? null,
        'codecInconnu' => $c->parametre('codec') === 'inconnu',
        'scripts' => ['/js/admin.js'],
    ], $statut);
}

/** GET /admin/chansons/{slug} */
function admin_chanson(Contexte $c, array $params): Reponse
{
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }

    return vue_chanson_admin($c, $ch, valeurs_chanson($ch, distribution($c->pdo(), (int) $ch['id'])), []);
}

/** POST /admin/chansons/{slug} : fiche et danseurs. */
function admin_enregistrer_chanson(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    $v = [];
    $e = [];
    foreach (['titre' => 80, 'film' => 80, 'entree_qui' => 200, 'entree_dou' => 200, 'musique_qui' => 200, 'note' => 500] as $nom => $max) {
        $v[$nom] = texte($c, $nom);
        if (mb_strlen($v[$nom]) > $max) {
            $e[$nom] = "Trop long : {$max} caractères au plus.";
        }
    }
    if ($v['titre'] === '') {
        $e['titre'] = 'Le titre est vide.';
    } else {
        $autre = $pdo->prepare('SELECT 1 FROM chansons WHERE titre = ? COLLATE NOCASE AND id <> ?');
        $autre->execute([$v['titre'], (int) $ch['id']]);
        if ($autre->fetchColumn() !== false) {
            $e['titre'] = 'Une autre chanson porte déjà ce titre.';
        }
    }
    $v['duree'] = texte($c, 'duree');
    $v['duree_coupee'] = texte($c, 'duree_coupee');
    $duree = $v['duree'] === '' ? null : lire_temps($v['duree']);
    $dureeCoupee = $v['duree_coupee'] === '' ? null : lire_temps($v['duree_coupee']);
    if ($v['duree'] !== '' && ($duree === null || $duree < 1 || $duree > 3600)) {
        $e['duree'] = 'Durée illisible : écris-la en minutes et secondes, par exemple 4:04.';
    }
    if ($v['duree_coupee'] !== '' && ($dureeCoupee === null || $dureeCoupee < 1 || $dureeCoupee > 3600)) {
        $e['duree_coupee'] = 'Durée illisible : écris-la en minutes et secondes, par exemple 2:30.';
    }
    $v['bpm'] = texte($c, 'bpm');
    $bpm = entier_facultatif($v['bpm'], 40, 240);
    if ($bpm === false) {
        $e['bpm'] = 'Tempo en temps par minute, de 40 à 240 (ou vide si inconnu).';
    }
    $v['bpm_verifie'] = $c->champ('bpm_verifie') === '1';
    $v['statut'] = (string) $c->champ('statut');
    if (!array_key_exists($v['statut'], STATUTS_CHANSON)) {
        $e['statut'] = 'Statut inconnu.';
    }
    $v['a_creer'] = $c->champ('a_creer') === '1';
    $v['ordre_passage'] = texte($c, 'ordre_passage');
    $ordre = entier_facultatif($v['ordre_passage'], 1, 99);
    if ($ordre === false) {
        $e['ordre_passage'] = 'Ordre de passage de 1 à 99 (ou vide si pas encore fixé).';
    }
    $cousins = \Chores\cousins_actifs($pdo);
    $v['danseurs'] = [];
    foreach ($cousins as $cousin) {
        $statut = (string) $c->champ('danseur_' . $cousin['id']);
        if ($statut === 'danse' || $statut === 'en_attente') {
            $v['danseurs'][$cousin['id']] = $statut;
        } elseif ($statut !== '') {
            $e['danseurs'] = 'Choix de danseur inconnu.';
        }
    }
    if ($duree !== null && !isset($e['duree'])) {
        $depasse = $pdo->prepare('SELECT nom FROM sections WHERE chanson_id = ? AND fin_s > ? ORDER BY fin_s DESC LIMIT 1');
        $depasse->execute([(int) $ch['id'], round($duree) + 0.05]);
        $nom = $depasse->fetchColumn();
        if ($nom !== false) {
            $e['duree'] = "La section « {$nom} » finit après cette durée : corrige d'abord la section.";
        }
    }
    if ($e !== []) {
        return vue_chanson_admin($c, $ch, $v, $e, [], [], null, 422);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE chansons SET titre = ?, film = ?, duree_s = ?, bpm = ?, bpm_verifie = ?, statut = ?, a_creer = ?, ordre_passage = ?,
             entree_qui = ?, entree_dou = ?, musique_qui = ?, duree_coupee_s = ?, note = ? WHERE id = ?'
        )->execute([
            $v['titre'], $v['film'] === '' ? null : $v['film'], $duree === null ? null : (int) round($duree), $bpm,
            $bpm !== null && $v['bpm_verifie'] ? 1 : 0, $v['statut'], $v['a_creer'] ? 1 : 0, $ordre,
            $v['entree_qui'] === '' ? null : $v['entree_qui'], $v['entree_dou'] === '' ? null : $v['entree_dou'],
            $v['musique_qui'] === '' ? null : $v['musique_qui'], $dureeCoupee === null ? null : (int) round($dureeCoupee),
            $v['note'] === '' ? null : $v['note'], (int) $ch['id'],
        ]);
        $pdo->prepare('DELETE FROM distributions WHERE chanson_id = ?')->execute([(int) $ch['id']]);
        $ajout = $pdo->prepare('INSERT INTO distributions (chanson_id, cousin_id, statut) VALUES (?, ?, ?)');
        foreach ($v['danseurs'] as $cousinId => $statut) {
            $ajout->execute([(int) $ch['id'], (int) $cousinId, $statut]);
        }
        // Un cousin retiré de la choré n'y garde ni point sur scène ni rôle de « qui s'en occupe ».
        $pdo->prepare(
            'DELETE FROM placements WHERE section_id IN (SELECT id FROM sections WHERE chanson_id = ?)
             AND cousin_id NOT IN (SELECT cousin_id FROM distributions WHERE chanson_id = ?)'
        )->execute([(int) $ch['id'], (int) $ch['id']]);
        $pdo->prepare(
            "UPDATE chansons SET createur_id = NULL WHERE id = ? AND createur_id NOT IN (SELECT cousin_id FROM distributions WHERE chanson_id = ? AND statut = 'danse')"
        )->execute([(int) $ch['id'], (int) $ch['id']]);
        $pdo->commit();
    } catch (\Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'chanson : ' . $v['titre']);

    return Reponse::redirection(url($c->config, '/admin/chansons/' . $ch['slug'] . '?ok=fiche'), 303);
}

/** POST /admin/chansons/{slug}/liens : ajouter (https seulement) ou supprimer un lien sortant. */
function admin_liens(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    if ($c->champ('action') === 'supprimer') {
        $id = (string) $c->champ('lien');
        if (preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
            $pdo->prepare('DELETE FROM liens WHERE id = ? AND chanson_id = ?')->execute([(int) $id, (int) $ch['id']]);
            journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'lien supprimé : ' . $ch['titre']);
        }

        return Reponse::redirection(url($c->config, '/admin/chansons/' . $ch['slug'] . '?ok=lien-supprime#liens'), 303);
    }
    $l = ['url' => texte($c, 'url'), 'titre' => texte($c, 'titre_lien'), 'duree' => texte($c, 'duree_lien'), 'type' => (string) $c->champ('type'), 'miroir' => (string) $c->champ('miroir')];
    $e = [];
    if (!lien_sortant_valide($l['url']) || mb_strlen($l['url']) > 300) {
        $e['url'] = 'L’adresse doit commencer par https:// (300 caractères au plus).';
    }
    if ($l['titre'] === '' || mb_strlen($l['titre']) > 120) {
        $e['titre_lien'] = 'Le titre est vide ou trop long : de 1 à 120 caractères.';
    }
    $duree = $l['duree'] === '' ? null : lire_temps($l['duree']);
    if ($l['duree'] !== '' && ($duree === null || $duree < 1 || $duree > 36000)) {
        $e['duree_lien'] = 'Durée illisible : par exemple 9:41.';
    }
    if (!array_key_exists($l['type'], TYPES_LIENS)) {
        $e['type'] = 'Type inconnu.';
    }
    if (!in_array($l['miroir'], ['', '0', '1'], true)) {
        $e['miroir'] = 'Choix inconnu.';
    }
    if ($e === []) {
        $existe = $pdo->prepare('SELECT 1 FROM liens WHERE chanson_id = ? AND url = ?');
        $existe->execute([(int) $ch['id'], $l['url']]);
        if ($existe->fetchColumn() !== false) {
            $e['url'] = 'Ce lien est déjà dans la liste.';
        }
    }
    if ($e !== []) {
        return vue_chanson_admin($c, $ch, valeurs_chanson($ch, distribution($pdo, (int) $ch['id'])), [], $l, $e, null, 422);
    }
    $pdo->prepare('INSERT INTO liens (chanson_id, url, titre, duree_s, type, en_miroir, partage_par, partage_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([(int) $ch['id'], $l['url'], $l['titre'], $duree === null ? null : (int) round($duree), $l['type'], $l['miroir'] === '' ? null : (int) $l['miroir'], 'Patrice', \Chores\aujourdhui_paris()]);
    journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'lien ajouté : ' . $ch['titre']);

    return Reponse::redirection(url($c->config, '/admin/chansons/' . $ch['slug'] . '?ok=lien#liens'), 303);
}

/**
 * POST /admin/chansons/{slug}/reference : dépose (ou retire) la vidéo de référence. MP4 H.264 déjà converti sur le
 * PC de Patrice (WebM accepté pour les essais) ; HEVC refusé en toutes lettres ; rien n'est converti ici.
 */
function admin_reference(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    $ancienne = null;
    $tailleAncienne = 0;
    if ($ch['video_ref_id'] !== null) {
        $requete = $pdo->prepare('SELECT id, fichier, taille_o FROM videos WHERE id = ? AND supprime_le IS NULL');
        $requete->execute([(int) $ch['video_ref_id']]);
        $ancienne = $requete->fetch() ?: null;
        // La nouvelle vidéo remplace l'ancienne : seule la différence de taille compte pour l'espace.
        $tailleAncienne = $ancienne !== null ? (int) $ancienne['taille_o'] : 0;
    }
    $retirer = static function () use ($pdo, $ancienne): void {
        if ($ancienne !== null) {
            $pdo->prepare('UPDATE videos SET supprime_le = ? WHERE id = ?')->execute([maintenant_utc(), (int) $ancienne['id']]);
            effacer_fichier_video((string) $ancienne['fichier']);
        }
    };
    if ($c->champ('action') === 'retirer') {
        $pdo->prepare('UPDATE chansons SET video_ref_id = NULL WHERE id = ?')->execute([(int) $ch['id']]);
        $retirer();
        journaliser($pdo, 'video_suppr', $c->cousin['id'] ?? null, 'référence retirée : ' . $ch['titre']);

        return Reponse::redirection(url($c->config, '/admin/chansons/' . $ch['slug'] . '?ok=retiree#reference'), 303);
    }

    $max = $c->config['TAILLE_MAX_UPLOAD_MO'] * 1024 * 1024;
    $fichier = isset($c->fichiers['video']) && is_array($c->fichiers['video']) ? $c->fichiers['video'] : null;
    $controle = controler_video($fichier, $max, ['video/mp4', 'video/webm']);
    $erreur = $controle['erreur'];
    if ($erreur === null && $controle['codec'] === 'hevc') {
        $erreur = 'Cette vidéo est en HEVC (le format « Haute efficacité » des iPhone), que beaucoup de téléphones Android et de PC ne lisent pas. Convertis-la en H.264 sur ton PC avec la commande de docs/ADMIN.md, puis dépose le fichier converti.';
    }
    if ($erreur === null && !espace_suffisant($pdo, $c->config, (int) $controle['taille'] - $tailleAncienne)) {
        $erreur = 'Plus de place : fais du ménage dans les vidéos avant d’en déposer une autre.';
    }
    if ($erreur !== null) {
        if (isset($controle['chemin']) && !$c->televersementSimule) {
            @unlink((string) $controle['chemin']);
        }

        return vue_chanson_admin($c, $ch, valeurs_chanson($ch, distribution($pdo, (int) $ch['id'])), [], [], [], $erreur, 422);
    }
    $nom = ranger_video($c, (string) $controle['chemin'], (string) $controle['extension']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO videos (chanson_id, cousin_id, type, titre, fichier, mime, taille_o, codec_devine, cree_le) VALUES (?, NULL, 'reference', ?, ?, ?, ?, ?, ?)"
        )->execute([(int) $ch['id'], mb_substr('Référence : ' . $ch['titre'], 0, 80), $nom, $controle['mime'], $controle['taille'], $controle['codec'], maintenant_utc()]);
        $videoId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE chansons SET video_ref_id = ? WHERE id = ?')->execute([$videoId, (int) $ch['id']]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        effacer_fichier_video($nom);
        throw $e;
    }
    $retirer();
    journaliser($pdo, 'video_ajout', $c->cousin['id'] ?? null, 'référence : ' . $ch['titre'] . ' (' . $controle['codec'] . ')');

    return Reponse::redirection(url($c->config, '/admin/chansons/' . $ch['slug'] . '?ok=reference' . ($controle['codec'] === 'inconnu' ? '&codec=inconnu' : '') . '#reference'), 303);
}

// --- Sections ----------------------------------------------------------------------------------------------

/** Page des sections (liste, formulaires, confirmation de suppression). */
function vue_sections(Contexte $c, array $ch, array $erreurs = [], array $saisies = [], int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $sections = sections_de($pdo, (int) $ch['id']);
    $aSupprimer = null;
    foreach ($sections as $s) {
        if ((string) $s['id'] === $c->parametre('supprimer')) {
            $aSupprimer = $s;
        }
    }
    $confirmations = [
        'ajoutee' => 'Section ajoutée.',
        'enregistree' => 'Section enregistrée.',
        'deplacee' => 'Ordre des sections changé.',
        'supprimee' => 'Section supprimée (avec ses niveaux et ses placements).',
    ];

    return vue_admin($c, 'sections', 'Sections de ' . $ch['titre'], [
        'chanson' => $ch,
        'sections' => $sections,
        'erreurs' => $erreurs,
        'saisies' => $saisies,
        'aSupprimer' => $aSupprimer,
        'video' => video_de_reference($c, $ch),
        'confirmation' => $confirmations[(string) $c->parametre('ok')] ?? null,
        'scripts' => ['/js/admin.js'],
    ], $statut);
}

/** GET /admin/chansons/{slug}/sections */
function admin_sections(Contexte $c, array $params): Reponse
{
    $ch = chanson_admin($c, $params);

    return $ch === null ? page_introuvable($c) : vue_sections($c, $ch);
}

/** Renumérote 1..n dans l'ordre donné. @param list<int> $ids */
function renumeroter(\PDO $pdo, array $ids): void
{
    $maj = $pdo->prepare('UPDATE sections SET ordre = ? WHERE id = ?');
    foreach (array_values($ids) as $i => $id) {
        $maj->execute([$i + 1, $id]);
    }
}

/** POST /admin/chansons/{slug}/sections : ajouter, enregistrer, monter, descendre, supprimer. */
function admin_modifier_sections(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    $action = (string) $c->champ('action');
    $ids = array_column(sections_de($pdo, (int) $ch['id']), 'id');
    $id = (string) $c->champ('id');
    $sectionId = preg_match('/^[1-9][0-9]{0,8}$/D', $id) && in_array((int) $id, $ids, true) ? (int) $id : null;
    $retour = static fn (string $ok, ?int $ancre = null): Reponse => Reponse::redirection(
        url($c->config, '/admin/chansons/' . $ch['slug'] . '/sections?ok=' . $ok . ($ancre !== null ? '#section-' . $ancre : '')),
        303
    );
    $champs = ['nom' => $c->champ('nom'), 'debut' => $c->champ('debut'), 'fin' => $c->champ('fin'), 'comptes' => $c->champ('comptes'), 'note' => $c->champ('note')];
    $qui = $c->cousin['id'] ?? null;

    if ($action === 'ajouter' || ($action === 'enregistrer' && $sectionId !== null)) {
        $cle = $action === 'ajouter' ? 'nouvelle' : (string) $sectionId;
        $resultat = valider_section($pdo, $ch, $champs, $sectionId);
        if ($resultat['erreurs'] !== []) {
            return vue_sections($c, $ch, [$cle => $resultat['erreurs']], [$cle => array_map('strval', $champs)], 422);
        }
        $v = $resultat['valeurs'];
        if ($action === 'ajouter') {
            $pdo->prepare('INSERT INTO sections (chanson_id, ordre, nom, debut_s, fin_s, comptes, note) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $ch['id'], count($ids) + 1, $v['nom'], $v['debut'], $v['fin'], $v['comptes'], $v['note']]);
            $sectionId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE sections SET nom = ?, debut_s = ?, fin_s = ?, comptes = ?, note = ? WHERE id = ?')
                ->execute([$v['nom'], $v['debut'], $v['fin'], $v['comptes'], $v['note'], $sectionId]);
        }
        journaliser($pdo, 'admin_modif', $qui, 'section ' . ($action === 'ajouter' ? 'ajoutée' : 'modifiée') . ' : ' . $ch['titre'] . ' · ' . $v['nom']);

        return $retour($action === 'ajouter' ? 'ajoutee' : 'enregistree', $sectionId);
    }
    if (($action === 'monter' || $action === 'descendre') && $sectionId !== null) {
        $i = (int) array_search($sectionId, $ids, true);
        $j = $action === 'monter' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            \Chores\en_transaction($pdo, static fn () => renumeroter($pdo, $ids));
            journaliser($pdo, 'admin_modif', $qui, 'ordre des sections : ' . $ch['titre']);
        }

        return $retour('deplacee', $sectionId);
    }
    if ($action === 'supprimer' && $sectionId !== null && $c->champ('confirmer') === '1') {
        \Chores\en_transaction($pdo, static function () use ($pdo, $sectionId, $ids): void {
            $pdo->prepare('DELETE FROM sections WHERE id = ?')->execute([$sectionId]);
            renumeroter($pdo, array_values(array_diff($ids, [$sectionId])));
        });
        journaliser($pdo, 'admin_modif', $qui, 'section supprimée : ' . $ch['titre']);

        return $retour('supprimee');
    }

    return vue_sections($c, $ch, ['general' => ['action' => 'Action inconnue : recharge la page et réessaie.']], [], 422);
}

// --- Placements --------------------------------------------------------------------------------------------

/** GET /admin/chansons/{slug}/placements?section=N */
function admin_placements(Contexte $c, array $params, array $erreurs = [], array $saisies = [], int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    $sections = sections_de($pdo, (int) $ch['id']);
    $courante = index_de_section($c, count($sections));
    $distribution = distribution($pdo, (int) $ch['id']);

    return vue_admin($c, 'placements', 'Placements de ' . $ch['titre'], [
        'chanson' => $ch,
        'sections' => $sections,
        'courante' => $courante,
        'distribution' => $distribution,
        'points' => $sections === [] ? [] : placements_section($pdo, $sections[$courante]['id']),
        'erreurs' => $erreurs,
        'saisies' => $saisies,
        'confirmation' => $c->parametre('ok') === '1' ? 'Placements enregistrés : tout le monde les voit.' : null,
    ], $statut);
}

/** POST /admin/chansons/{slug}/placements : positions x/y (0-100, vide = non placé), flèches, copie. */
function admin_enregistrer_placements(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ch = chanson_admin($c, $params);
    if ($ch === null) {
        return page_introuvable($c);
    }
    $sections = sections_de($pdo, (int) $ch['id']);
    $index = array_search((int) $c->champ('section_id'), array_column($sections, 'id'), true);
    if ($index === false) {
        return page_introuvable($c);
    }
    $section = $sections[$index];
    $retour = url($c->config, '/admin/chansons/' . $ch['slug'] . '/placements?section=' . ($index + 1) . '&ok=1#placements-formulaire');
    $distribution = distribution($pdo, (int) $ch['id']);
    $qui = $c->cousin['id'] ?? null;

    if ($c->champ('action') === 'copier' && ($index === 0 || placements_section($pdo, $sections[$index - 1]['id']) === [])) {
        // La copie remplace tout : depuis une section vide (ou avant la première), elle effacerait sans rien apporter.
        return admin_placements($c, $params, ['copie' => 'La section précédente n’a encore aucun point : rien n’a été copié, rien n’a été effacé.'], [], 422);
    }
    if ($c->champ('action') === 'copier') {
        \Chores\en_transaction($pdo, static function () use ($pdo, $section, $sections, $index, $ch): void {
            $pdo->prepare('DELETE FROM placements WHERE section_id = ?')->execute([$section['id']]);
            // Seuls les danseurs actuels de la chanson sont recopiés.
            $pdo->prepare(
                'INSERT INTO placements (section_id, cousin_id, x, y) SELECT ?, p.cousin_id, p.x, p.y FROM placements p
                 JOIN distributions d ON d.cousin_id = p.cousin_id AND d.chanson_id = ? WHERE p.section_id = ?'
            )->execute([$section['id'], (int) $ch['id'], $sections[$index - 1]['id']]);
        });
        journaliser($pdo, 'placement', $qui, 'administration : ' . $ch['titre'] . ' · ' . $section['nom'] . ' copiée de la précédente');

        return Reponse::redirection($retour, 303);
    }

    $erreurs = [];
    $saisies = [];
    $positions = [];
    foreach ($distribution as $d) {
        $x = texte($c, 'x_' . $d['cousin_id']);
        $y = texte($c, 'y_' . $d['cousin_id']);
        $saisies[$d['cousin_id']] = ['x' => $x, 'y' => $y];
        if ($x === '' && $y === '') {
            $positions[$d['cousin_id']] = null;
            continue;
        }
        $nx = entier_facultatif($x, 0, 100);
        $ny = entier_facultatif($y, 0, 100);
        if (!is_int($nx) || !is_int($ny)) {
            $erreurs[$d['cousin_id']] = $d['prenom'] . ' : x et y vont de 0 à 100 (les deux vides : non placé).';
            continue;
        }
        $positions[$d['cousin_id']] = [$nx, $ny];
    }
    // Flèche « id:direction » : un pas de 5 (un danseur non placé part du centre).
    if (preg_match('/^([1-9][0-9]{0,8}):([a-z]+)$/D', (string) $c->champ('fleche'), $m) && array_key_exists((int) $m[1], $positions) && array_key_exists($m[2], DIRECTIONS)) {
        [$x0, $y0] = $positions[(int) $m[1]] ?? [50, 50];
        $positions[(int) $m[1]] = deplacer($x0, $y0, $m[2]);
    }
    if ($erreurs !== []) {
        return admin_placements($c, $params, $erreurs, $saisies, 422);
    }
    $pdo->beginTransaction();
    try {
        $supprimer = $pdo->prepare('DELETE FROM placements WHERE section_id = ? AND cousin_id = ?');
        $poser = $pdo->prepare('INSERT INTO placements (section_id, cousin_id, x, y) VALUES (?, ?, ?, ?) ON CONFLICT(section_id, cousin_id) DO UPDATE SET x = excluded.x, y = excluded.y');
        foreach ($positions as $cousinId => $p) {
            if ($p === null) {
                $supprimer->execute([$section['id'], $cousinId]);
            } else {
                $poser->execute([$section['id'], $cousinId, $p[0], $p[1]]);
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    journaliser($pdo, 'placement', $qui, 'administration : ' . $ch['titre'] . ' · ' . $section['nom']);

    return Reponse::redirection($retour, 303);
}

// --- Journal -----------------------------------------------------------------------------------------------

/** GET /admin/journal : les 200 dernières lignes. */
function admin_journal(Contexte $c, array $params): Reponse
{
    return vue_admin($c, 'journal', 'Journal', ['journal' => dernieres_lignes_journal($c->pdo(), 200)]);
}
