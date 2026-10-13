<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\adresse_media;
use function Chores\aujourdhui_paris;
use function Chores\bilan_repetition;
use function Chores\chanson_par_slug;
use function Chores\checklist;
use function Chores\chansons_a_plusieurs;
use function Chores\commentaires_de;
use function Chores\controler_video;
use function Chores\cookie_admin_valide;
use function Chores\date_en_mots;
use function Chores\de;
use function Chores\debit_autorise;
use function Chores\debit_reserver;
use function Chores\effacer_fichier_video;
use function Chores\espace_suffisant;
use function Chores\format_temps;
use function Chores\journaliser;
use function Chores\ligne_checklist;
use function Chores\lire_instant;
use function Chores\ma_reponse;
use function Chores\maintenant_utc;
use function Chores\ordre_de_passage;
use function Chores\page_erreur;
use function Chores\page_introuvable;
use function Chores\poser_presence;
use function Chores\quand_repetition;
use function Chores\ranger_video;
use function Chores\reglage;
use function Chores\rendre;
use function Chores\repetition_ouverte;
use function Chores\repetition_par_id;
use function Chores\repetitions;
use function Chores\reponse_json;
use function Chores\reponse_trop_d_essais;
use function Chores\toutes_les_chansons;
use function Chores\url;
use function Chores\video_de_cousin;
use function Chores\videos_des_cousins;

use const Chores\COOKIE_ADMIN;
use const Chores\REPONSES_PRESENCE;

// Pages du groupe (phase 5, spec § 2, 7) : répétitions et présences, vidéos des cousins (album, téléversement, une
// vidéo, commentaires horodatés, suppression), Jour J (frise, check-list). Tout marche sans JavaScript.

/** 5 vidéos par heure et 20 par jour par prénom ; 10 par heure par adresse (hachée) ; 30 commentaires par heure. */
const LIMITES_VIDEOS_PRENOM = [['heure', 5], ['jour', 20]];
const LIMITES_VIDEOS_IP = [['heure', 10]];
const LIMITES_COMMENTAIRES = [['heure', 30]];
const TITRE_MAX = 80;
const NOTE_MAX = 300;
const COMMENTAIRE_MAX = 200;

/** Patrice sur cet appareil : le cookie d'administration (n'importe qui peut choisir le prénom « Patrice »). */
function appareil_de_patrice(Contexte $c): bool
{
    return cookie_admin_valide($c->config['SECRET'], $c->config['CLE_ADMIN'], $c->cookie(COOKIE_ADMIN));
}

/** Rend une page du groupe avec la barre « Retour » vers Ma page. */
function vue_groupe(Contexte $c, string $vue, string $titre, array $donnees, int $statut = 200): Reponse
{
    return rendre($c, $vue, $donnees + [
        'titre' => $titre,
        'barre' => 'Chorés du mariage',
        'barreAction' => 'retour',
        'barreRetour' => url($c->config, '/moi'),
    ], $statut);
}

// --- Répétitions -------------------------------------------------------------------------------------------

/** Phrase de confirmation après une réponse (?ok=oui|non&r=id), lue sur Ma page et sur /repetitions. */
function confirmation_presence(Contexte $c): ?string
{
    $ok = $c->parametre('ok');
    $r = repetition_par_id($c->pdo(), (string) $c->parametre('r'));
    // Seulement si c'est vraiment la réponse enregistrée : un lien « ?ok=oui » envoyé par un autre ne fait rien croire.
    if (!in_array($ok, ['oui', 'non'], true) || $r === null || ma_reponse($c->pdo(), (int) $r['id'], (int) $c->cousin['id']) !== $ok) {
        return null;
    }
    $jour = date_en_mots((string) $r['date'], aujourdhui_paris());

    return $ok === 'oui' ? "C’est noté : tu viens {$jour}." : "C’est noté : tu ne peux pas venir {$jour}.";
}

/** GET /repetitions */
function page_repetitions(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $aujourdhui = aujourdhui_paris();
    $moi = (int) $c->cousin['id'];
    $avenir = [];
    $passees = [];
    foreach (repetitions($pdo) as $r) {
        $ligne = [
            'repetition' => $r,
            'bilan' => bilan_repetition($pdo, (int) $r['id']),
            'maReponse' => ma_reponse($pdo, (int) $r['id'], $moi),
            'ouverte' => repetition_ouverte($r, $aujourdhui),
        ];
        if ($r['statut'] !== 'passee' && (string) $r['date'] >= $aujourdhui) {
            $avenir[] = $ligne;
        } else {
            $passees[] = $ligne;
        }
    }
    $rang = ['decidee' => 0, 'proposee' => 1, 'annulee' => 2];
    usort($avenir, static fn (array $a, array $b): int => [$rang[$a['repetition']['statut']] ?? 3, $a['repetition']['date']] <=> [$rang[$b['repetition']['statut']] ?? 3, $b['repetition']['date']]);

    return vue_groupe($c, 'repetitions', 'Répétitions', [
        'avenir' => $avenir,
        'passees' => array_reverse($passees),
        'aujourdhui' => $aujourdhui,
        'lienSondage' => reglage($pdo, 'lien_sondage'),
        'confirmation' => confirmation_presence($c),
    ]);
}

/** POST /repetitions/{id}/presence : « oui » ou « non » pour moi (idempotent). */
function repondre_presence(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $r = repetition_par_id($pdo, $params['id'] ?? '');
    if ($r === null) {
        return page_introuvable($c);
    }
    $reponse = (string) $c->champ('reponse');
    if (!array_key_exists($reponse, REPONSES_PRESENCE)) {
        return page_erreur($c->config, 422, 'Réponse inconnue', 'Choisis « Je viens » ou « Je ne peux pas ».');
    }
    $aujourdhui = aujourdhui_paris();
    if (!repetition_ouverte($r, $aujourdhui)) {
        $texte = $r['statut'] === 'annulee'
            ? 'Cette répétition est annulée : plus besoin de répondre.'
            : 'Cette répétition est passée : plus besoin de répondre.';

        return page_erreur($c->config, 409, 'Plus de réponse possible', $texte);
    }
    if (!ecriture_autorisee($c)) {
        return reponse_trop_d_essais($c, 'ecriture');
    }
    $moi = (int) $c->cousin['id'];
    if (poser_presence($pdo, (int) $r['id'], $moi, $reponse)) {
        journaliser($pdo, 'presence', $moi, quand_repetition($r, $aujourdhui) . ' : ' . REPONSES_PRESENCE[$reponse]);
    }
    $retour = $c->champ('retour') === 'moi' ? '/moi?ok=' . $reponse . '&r=' . (int) $r['id'] . '#prochaine-repetition' : '/repetitions?ok=' . $reponse . '&r=' . (int) $r['id'] . '#repetition-' . (int) $r['id'];

    return Reponse::redirection(url($c->config, $retour), 303);
}

// --- Vidéos des cousins ------------------------------------------------------------------------------------

/**
 * Carte d'une vidéo telle que le composant carte-video l'attend.
 * @param array<string, mixed> $v
 */
function carte_de_video(Contexte $c, array $v, int $niveau = 3): array
{
    $page = url($c->config, '/videos/' . (int) $v['id']);

    return [
        'niveau' => $niveau,
        'chanson' => (string) $v['chanson_titre'],
        'duree' => $v['duree_s'] !== null ? format_temps((float) $v['duree_s']) : null,
        'auteur' => (string) ($v['auteur'] ?? 'Un cousin'),
        'titre' => (string) $v['titre'],
        'date' => \Chores\date_paris((string) $v['cree_le']),
        'note' => $v['note'],
        'format' => \Chores\video_peut_etre_illisible($v) ? \Chores\MOT_FORMAT_IPHONE : null,
        'href' => $page,
        'commentaires' => array_map(static fn (array $m): array => [
            'temps' => $m['temps'],
            'auteur' => $m['auteur'],
            'texte' => $m['texte'],
            'href' => $page . ($m['temps'] !== null ? '?t=' . (int) floor($m['temps']) : '') . '#lecteur-video',
        ], commentaires_de($c->pdo(), (int) $v['id'])),
    ];
}

/** GET /videos : un album par chanson. */
function page_videos(Contexte $c, array $params): Reponse
{
    $albums = [];
    foreach (videos_des_cousins($c->pdo()) as $v) {
        $albums[(string) $v['chanson_slug']] ??= ['titre' => (string) $v['chanson_titre'], 'videos' => []];
        $albums[(string) $v['chanson_slug']]['videos'][] = carte_de_video($c, $v);
    }

    return vue_groupe($c, 'videos', 'Vidéos des cousins', [
        'albums' => array_values($albums),
        'confirmation' => $c->parametre('ok') === 'supprimee' ? 'Ta vidéo est supprimée : le fichier est effacé du serveur.' : null,
    ]);
}

/** Chansons proposées au téléversement (hors écartées), titre par slug. @return array<string, string> */
function chansons_pour_videos(Contexte $c): array
{
    $options = [];
    foreach (toutes_les_chansons($c->pdo()) as $ch) {
        if ($ch['statut'] !== 'ecartee') {
            $options[(string) $ch['slug']] = (string) $ch['titre'];
        }
    }

    return $options;
}

/** Formulaire de téléversement (rendu à nouveau, valeurs gardées, en cas d'erreur). */
function vue_ajouter_video(Contexte $c, array $valeurs, array $erreurs, int $statut = 200): Reponse
{
    return vue_groupe($c, 'videos-ajouter', 'Ajouter ma vidéo', [
        'barreRetour' => url($c->config, '/videos'),
        'scripts' => ['/js/televersement.js'],
        'chansons' => chansons_pour_videos($c),
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'maxMo' => (int) $c->config['TAILLE_MAX_UPLOAD_MO'],
    ], $statut);
}

/** GET /videos/ajouter[?chanson=slug] */
function formulaire_video(Contexte $c, array $params): Reponse
{
    $slug = (string) $c->parametre('chanson');

    return vue_ajouter_video($c, [
        'chanson' => array_key_exists($slug, chansons_pour_videos($c)) ? $slug : '',
        'titre' => '',
        'note' => '',
        'en_miroir' => false,
    ], []);
}

/** POST /videos : contrôle des champs et du fichier, débit compté sur les envois valides, rangement, journal. */
function ajouter_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $valeurs = [
        'chanson' => trim((string) $c->champ('chanson')),
        'titre' => trim((string) $c->champ('titre')),
        'note' => trim((string) $c->champ('note')),
        'en_miroir' => $c->champ('en_miroir') === '1',
    ];
    $erreurs = [];
    $chanson = array_key_exists($valeurs['chanson'], chansons_pour_videos($c)) ? chanson_par_slug($pdo, $valeurs['chanson']) : null;
    if ($chanson === null) {
        $erreurs['video-chanson'] = 'Choisis la chanson de ta vidéo.';
    }
    if ($valeurs['titre'] === '' || mb_strlen($valeurs['titre']) > TITRE_MAX) {
        $erreurs['video-titre'] = 'Donne un titre à ta vidéo, de 1 à ' . TITRE_MAX . ' caractères.';
    }
    if (mb_strlen($valeurs['note']) > NOTE_MAX) {
        $erreurs['video-note'] = 'La note dépasse ' . NOTE_MAX . ' caractères.';
    }
    $fichier = isset($c->fichiers['video']) && is_array($c->fichiers['video']) ? $c->fichiers['video'] : null;
    $controle = controler_video($fichier, (int) $c->config['TAILLE_MAX_UPLOAD_MO'] * 1024 * 1024, array_keys(\Chores\TYPES_VIDEO));
    if ($controle['erreur'] !== null) {
        $erreurs['video-fichier'] = $controle['erreur'];
    } elseif (!espace_suffisant($pdo, $c->config, (int) $controle['taille'])) {
        $erreurs['video-fichier'] = 'Plus de place : demande à Patrice de faire du ménage.';
    }
    // Un fichier refusé n'est jamais déplacé : PHP efface lui-même le fichier temporaire à la fin de la requête.
    if ($erreurs !== []) {
        if ($c->veutJson()) {
            return reponse_json(['erreurs' => $erreurs], 422);
        }

        return vue_ajouter_video($c, $valeurs, $erreurs, 422);
    }
    $moi = (int) $c->cousin['id'];
    // Débit compté sur les envois valides et acceptés seulement : un fichier refusé, ou un envoi refusé par une des
    // limites, ne consomme aucun essai.
    if (!debit_reserver($pdo, $c->config['SECRET'], [
        ['video', 'cousin-' . $moi, LIMITES_VIDEOS_PRENOM],
        ['video-ip', $c->ip, LIMITES_VIDEOS_IP],
    ])) {
        if ($c->veutJson()) {
            \Chores\journaliser_refus_debit($c, 'video');
            return reponse_json(['erreurs' => ['video-fichier' => 'Tu as déjà envoyé beaucoup de vidéos : réessaie dans une heure.']], 429)
                ->avecEntete('Retry-After', (string) (3600 - time() % 3600));
        }

        return reponse_trop_d_essais($c, 'video');
    }
    $nom = ranger_video($c, (string) $controle['chemin'], (string) $controle['extension']);
    try {
        $pdo->prepare(
            "INSERT INTO videos (chanson_id, cousin_id, type, titre, note, fichier, mime, taille_o, codec_devine, en_miroir, cree_le)
             VALUES (?, ?, 'cousin', ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            (int) $chanson['id'], $moi, $valeurs['titre'], $valeurs['note'] === '' ? null : $valeurs['note'], $nom,
            $controle['mime'], $controle['taille'], $controle['codec'], $valeurs['en_miroir'] ? 1 : 0, maintenant_utc(),
        ]);
    } catch (\Throwable $e) {
        effacer_fichier_video($nom);
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    journaliser($pdo, 'video_ajout', $moi, $chanson['titre'] . ' : ' . $valeurs['titre'] . ' (' . $controle['codec'] . ')');
    $adresse = url($c->config, '/videos/' . $id . '?ok=ajoutee');
    if ($c->veutJson()) {
        return reponse_json(['url' => $adresse], 201);
    }

    return Reponse::redirection($adresse, 303);
}

/** GET /videos/{id}[?t=secondes] */
function page_video(Contexte $c, array $params, array $erreurs = [], array $saisie = [], int $statut = 200): Reponse
{
    $pdo = $c->pdo();
    $video = video_de_cousin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return page_introuvable($c);
    }
    $moi = (int) $c->cousin['id'];
    $estAuteur = $video['cousin_id'] !== null && (int) $video['cousin_id'] === $moi;
    $patrice = appareil_de_patrice($c);
    $t = $c->parametre('t');
    $instant = $t !== null && preg_match('/^\d{1,5}$/D', $t) ? (int) $t : null;
    $commentaires = commentaires_de($pdo, (int) $video['id']);
    $aSupprimer = $c->parametre('supprimer-commentaire');
    $commentaireASupprimer = null;
    foreach ($commentaires as $m) {
        if ((string) $m['id'] === $aSupprimer && ($m['cousin_id'] === $moi || $patrice)) {
            $commentaireASupprimer = $m;
        }
    }
    $ok = (string) $c->parametre('ok');
    // Les confirmations des commentaires s'affichent dans « Commentaires », là où la page s'ouvre.
    $confirmationsCommentaire = ['commentaire' => 'Ton commentaire est ajouté.', 'commentaire-supprime' => 'Le commentaire est supprimé.'];

    return vue_groupe($c, 'video', $video['auteur'] . ' · ' . $video['titre'], [
        'barreRetour' => url($c->config, '/videos'),
        'scripts' => ['/js/video.js'],
        'video' => $video,
        'src' => adresse_media($c->config, (int) $video['id'], (string) $video['fichier']),
        'instant' => $instant,
        'commentaires' => $commentaires,
        'estAuteur' => $estAuteur,
        'moi' => $moi,
        'patrice' => $patrice,
        'confirmerSuppression' => $c->parametre('supprimer') === '1' && ($estAuteur || $patrice),
        'commentaireASupprimer' => $commentaireASupprimer,
        'erreurs' => $erreurs,
        'saisie' => $saisie + ['temps' => $instant !== null ? format_temps($instant) : '', 'texte' => ''],
        'confirmation' => $ok === 'ajoutee' ? 'Ta vidéo est en ligne : les cousins la voient dans « Vidéos des cousins ».' : null,
        'confirmationCommentaire' => $confirmationsCommentaire[$ok] ?? null,
    ], $statut);
}

/** POST /videos/{id}/commentaires : instant facultatif (« 0:42 ») et texte de 1 à 200 caractères. */
function commenter_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_de_cousin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return page_introuvable($c);
    }
    $saisie = ['temps' => trim((string) $c->champ('temps')), 'texte' => trim((string) $c->champ('texte'))];
    $erreurs = [];
    $temps = null;
    if ($saisie['temps'] !== '') {
        $temps = lire_instant($saisie['temps']);
        if ($temps === null) {
            $erreurs['commentaire-temps'] = 'L’instant est illisible : écris-le en minutes et secondes, par exemple 0:42.';
        } elseif ($video['duree_s'] !== null && $temps > (float) $video['duree_s'] + 1) {
            $erreurs['commentaire-temps'] = 'L’instant dépasse la durée de la vidéo (' . format_temps((float) $video['duree_s']) . ').';
        }
    }
    if ($saisie['texte'] === '' || mb_strlen($saisie['texte']) > COMMENTAIRE_MAX) {
        $erreurs['commentaire-texte'] = 'Écris ton commentaire, de 1 à ' . COMMENTAIRE_MAX . ' caractères.';
    }
    if ($erreurs !== []) {
        return page_video($c, $params, $erreurs, $saisie, 422);
    }
    $moi = (int) $c->cousin['id'];
    if (!debit_autorise($pdo, $c->config['SECRET'], 'commentaire', 'cousin-' . $moi, LIMITES_COMMENTAIRES)) {
        return reponse_trop_d_essais($c, 'commentaire');
    }
    $pdo->prepare('INSERT INTO commentaires (video_id, cousin_id, temps_s, texte, cree_le) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $video['id'], $moi, $temps, $saisie['texte'], maintenant_utc()]);
    $id = (int) $pdo->lastInsertId();
    journaliser($pdo, 'commentaire', $moi, 'vidéo ' . de($video['auteur'] ?? 'un cousin') . ($temps !== null ? ' à ' . format_temps($temps) : ''));

    return Reponse::redirection(url($c->config, '/videos/' . (int) $video['id'] . '?ok=commentaire' . ($temps !== null ? '&t=' . (int) floor($temps) : '') . '#commentaire-' . $id), 303);
}

/** POST /videos/{id}/commentaires/{commentaire}/supprimer : auteur du commentaire ou Patrice. */
function supprimer_commentaire(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_de_cousin($pdo, $params['id'] ?? '');
    $commentaire = null;
    foreach ($video !== null ? commentaires_de($pdo, (int) $video['id']) : [] as $m) {
        if ((string) $m['id'] === ($params['commentaire'] ?? '')) {
            $commentaire = $m;
        }
    }
    if ($video === null || $commentaire === null) {
        return page_introuvable($c);
    }
    $moi = (int) $c->cousin['id'];
    if ($commentaire['cousin_id'] !== $moi && !appareil_de_patrice($c)) {
        return page_erreur($c->config, 403, 'Pas ton commentaire', 'Seuls l’auteur du commentaire et Patrice peuvent le supprimer.');
    }
    $pdo->prepare('UPDATE commentaires SET supprime_le = ? WHERE id = ? AND supprime_le IS NULL')->execute([maintenant_utc(), $commentaire['id']]);
    journaliser($pdo, 'commentaire', $moi, 'commentaire supprimé sur la vidéo ' . de($video['auteur'] ?? 'un cousin'));

    return Reponse::redirection(url($c->config, '/videos/' . (int) $video['id'] . '?ok=commentaire-supprime#commentaires-titre'), 303);
}

/** POST /videos/{id}/supprimer : auteur ou Patrice ; le fichier est effacé, la ligne marquée supprimée. */
function supprimer_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_de_cousin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return page_introuvable($c);
    }
    $moi = (int) $c->cousin['id'];
    if (($video['cousin_id'] === null || (int) $video['cousin_id'] !== $moi) && !appareil_de_patrice($c)) {
        return page_erreur($c->config, 403, 'Pas ta vidéo', 'Seuls l’auteur de la vidéo et Patrice peuvent la supprimer.');
    }
    $fichier = \Chores\marquer_video_supprimee($pdo, (int) $video['id']);
    if ($fichier !== null) {
        effacer_fichier_video($fichier);
    }
    journaliser($pdo, 'video_suppr', $moi, $video['chanson_titre'] . ' : ' . $video['titre'] . ' (de ' . ($video['auteur'] ?? 'un cousin') . ')');

    return Reponse::redirection(url($c->config, '/videos?ok=supprimee'), 303);
}

/** POST /videos/{id}/duree : durée lue par le navigateur de l'auteur au premier visionnage (une seule fois). */
function poser_duree_video(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $video = video_de_cousin($pdo, $params['id'] ?? '');
    if ($video === null) {
        return reponse_json(['erreur' => 'Vidéo introuvable.'], 404);
    }
    if ($video['cousin_id'] === null || (int) $video['cousin_id'] !== (int) $c->cousin['id']) {
        return reponse_json(['erreur' => 'Seul l’auteur pose la durée.'], 403);
    }
    $texte = (string) $c->champ('duree');
    $duree = preg_match('/^\d{1,5}(\.\d{1,3})?$/D', $texte) ? (float) $texte : -1.0;
    if ($duree <= 0 || $duree > 36000) {
        return reponse_json(['erreur' => 'Durée illisible.'], 422);
    }
    if (!ecriture_autorisee($c)) {
        \Chores\journaliser_refus_debit($c, 'ecriture');
        return reponse_json(['erreur' => 'Trop de changements d’un coup.'], 429);
    }
    $pdo->prepare('UPDATE videos SET duree_s = ? WHERE id = ? AND duree_s IS NULL')->execute([round($duree, 2), (int) $video['id']]);

    return reponse_json(['duree' => format_temps($duree)]);
}

// --- Jour J ------------------------------------------------------------------------------------------------

/** GET /jour-j */
function page_jour_j(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ordre = ordre_de_passage($pdo);
    // Mode scène : la première chanson à plusieurs de l'ordre de passage (sinon la première à plusieurs).
    $aPlusieurs = array_column(chansons_a_plusieurs($pdo), 'slug');
    $scene = null;
    foreach (array_merge(array_column($ordre, 'slug'), $aPlusieurs) as $slug) {
        if (in_array($slug, $aPlusieurs, true)) {
            $scene = $slug;
            break;
        }
    }
    $date = reglage($pdo, 'date_mariage');

    return vue_groupe($c, 'jour-j', 'Jour J', [
        'date' => $date !== null ? date_en_mots($date, '') : '[date]',
        'lieu' => reglage($pdo, 'lieu_mariage') ?? '[lieu]',
        'dureeDanses' => reglage($pdo, 'duree_danses_min'),
        'ordre' => $ordre,
        'scene' => $scene !== null ? url($c->config, '/placements/' . $scene . '?scene=1&depuis=jour-j#scene') : null,
        'checklist' => checklist($pdo),
        'confirmation' => $c->parametre('ok') === 'check' ? 'C’est noté dans la check-list.' : null,
    ]);
}

/** POST /jour-j/check/{id} : `fait` = 1 ou 0 (l'état voulu, pas une bascule : deux cousins ne s'annulent pas). */
function cocher_checklist(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $ligne = ligne_checklist($pdo, $params['id'] ?? '');
    if ($ligne === null) {
        return page_introuvable($c);
    }
    $fait = $c->champ('fait');
    if (!in_array($fait, ['0', '1'], true)) {
        return page_erreur($c->config, 422, 'Choix inconnu', 'Touche la ligne pour la marquer « fait » ou « à faire ».');
    }
    if (!ecriture_autorisee($c)) {
        return reponse_trop_d_essais($c, 'ecriture');
    }
    $moi = (int) $c->cousin['id'];
    if ((int) $ligne['fait'] !== (int) $fait) {
        $pdo->prepare('UPDATE checklist SET fait = ?, fait_par = ?, fait_le = ? WHERE id = ?')
            ->execute([(int) $fait, $fait === '1' ? $moi : null, $fait === '1' ? maintenant_utc() : null, (int) $ligne['id']]);
        journaliser($pdo, 'check', $moi, $ligne['libelle'] . ' : ' . ($fait === '1' ? 'fait' : 'à faire'));
    }

    return Reponse::redirection(url($c->config, '/jour-j?ok=check#check-' . (int) $ligne['id']), 303);
}
