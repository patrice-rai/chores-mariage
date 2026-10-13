<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\aujourdhui_paris;
use function Chores\ce_qui_bouge;
use function Chores\chanson_par_slug;
use function Chores\chanson_pour_lecteur;
use function Chores\chansons_a_plusieurs;
use function Chores\chansons_du_cousin;
use function Chores\chansons_proposees_sans_danseur;
use function Chores\debit_autorise;
use function Chores\deplacer;
use function Chores\distribution;
use function Chores\grille_maitrise;
use function Chores\journaliser;
use function Chores\liens_de;
use function Chores\maintenant_utc;
use function Chores\niveaux_du_cousin;
use function Chores\page_erreur;
use function Chores\page_introuvable;
use function Chores\placements_section;
use function Chores\prochaine_repetition;
use function Chores\progression_du_cousin;
use function Chores\reglage;
use function Chores\rendre;
use function Chores\reponse_json;
use function Chores\reponse_trop_d_essais;
use function Chores\reponses_repetition;
use function Chores\repetitions;
use function Chores\sections_de;
use function Chores\servir_fichier;
use function Chores\texte_progression;
use function Chores\url;
use function Chores\version_lien;

use const Chores\DIRECTIONS;

// Pages des cousins (phase 4) : Ma page, page d'une chanson, niveaux, « qui s'en occupe », mode répétition,
// placements, répétitions en lecture, pages « Bientôt », flux vidéo /m/{id}, état JSON pour le lecteur.

/** Limite commune aux petites écritures d'un cousin (niveaux, créateur, placements) : 300 par heure par prénom. */
const LIMITE_ECRITURES = [['heure', 300]];

function ecriture_autorisee(Contexte $c): bool
{
    return debit_autorise($c->pdo(), $c->config['SECRET'], 'ecriture', 'cousin-' . ($c->cousin['id'] ?? 0), LIMITE_ECRITURES);
}

/** Statut du cousin courant dans une distribution : « danse », « en_attente » ou null. */
function ma_place_dans(Contexte $c, array $distribution): ?string
{
    foreach ($distribution as $d) {
        if ($d['cousin_id'] === ($c->cousin['id'] ?? 0)) {
            return $d['statut'];
        }
    }

    return null;
}

/** GET /moi */
function ma_page(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $moi = (int) $c->cousin['id'];
    $chores = [];
    foreach (chansons_du_cousin($pdo, $moi) as $chanson) {
        [$maitrisees, $total] = progression_du_cousin($pdo, $moi, (int) $chanson['id']);
        $chores[] = ['chanson' => $chanson, 'distribution' => distribution($pdo, (int) $chanson['id']), 'maitrisees' => $maitrisees, 'total' => $total];
    }
    $aujourdhui = aujourdhui_paris();
    $prochaine = prochaine_repetition($pdo, $aujourdhui);

    return rendre($c, 'moi', [
        'titre' => 'Ma page',
        'barre' => 'Chorés du mariage',
        'chores' => $chores,
        'prochaine' => $prochaine,
        'maReponse' => $prochaine !== null ? \Chores\ma_reponse($pdo, (int) $prochaine['repetition']['id'], $moi) : null,
        'confirmation' => confirmation_presence($c),
        'aujourdhui' => $aujourdhui,
        'lienSondage' => reglage($pdo, 'lien_sondage'),
        'proposees' => chansons_proposees_sans_danseur($pdo),
        'fil' => ce_qui_bouge($pdo),
    ]);
}

/** GET /chanson/{slug}?section=N */
function page_chanson(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, $params['slug'] ?? '');
    if ($chanson === null) {
        return page_introuvable($c);
    }
    $id = (int) $chanson['id'];
    $distribution = distribution($pdo, $id);
    $sections = sections_de($pdo, $id);
    $courante = index_de_section($c, count($sections));
    $placements = array_map(static fn (array $s): array => placements_section($pdo, $s['id']), $sections);
    [$maitrisees, $total] = progression_du_cousin($pdo, (int) $c->cousin['id'], $id);
    $reference = reference_a_garder($c, $chanson);

    return rendre($c, 'chanson', [
        'titre' => (string) $chanson['titre'],
        'barre' => 'Chorés du mariage',
        'barreAction' => 'retour',
        'barreRetour' => url($c->config, '/moi'),
        'scripts' => ['/js/lecteur.js', '/js/chanson.js'],
        'chanson' => $chanson,
        'distribution' => $distribution,
        'maPlace' => ma_place_dans($c, $distribution),
        'sections' => $sections,
        'courante' => $courante,
        'niveaux' => niveaux_du_cousin($pdo, (int) $c->cousin['id'], $id),
        'grille' => grille_maitrise($pdo, $id, $sections, $distribution),
        'placements' => $placements,
        'liens' => liens_de($pdo, $id),
        'video' => $reference['url'] ?? null,
        'createur' => createur($pdo, $chanson),
        'maitrisees' => $maitrisees,
        'total' => $total,
        'aPlusieurs' => in_array($chanson['slug'], array_column(chansons_a_plusieurs($pdo), 'slug'), true),
        'videos' => \Chores\videos_des_cousins($pdo, $id),
        'reference' => $reference,
        'confirmation' => $c->parametre('ok') === 'createur' ? 'C’est noté pour « qui s’en occupe ».' : null,
    ]);
}

/** Adresse de la vidéo de référence (null si Patrice ne l'a pas déposée, ou si elle a été retirée). */
function video_de_reference(Contexte $c, array $chanson): ?string
{
    return reference_a_garder($c, $chanson)['url'] ?? null;
}

/**
 * Vidéo de référence telle que « Garder sur mon téléphone » la décrit : adresse (nom interne compris, une vidéo
 * remplacée n'est jamais resservie d'un cache), taille en octets.
 * @return array{url: string, taille: int}|null
 */
function reference_a_garder(Contexte $c, array $chanson): ?array
{
    if ($chanson['video_ref_id'] === null) {
        return null;
    }
    $requete = $c->pdo()->prepare('SELECT id, fichier, taille_o FROM videos WHERE id = ? AND supprime_le IS NULL');
    $requete->execute([(int) $chanson['video_ref_id']]);
    $video = $requete->fetch();

    return $video === false ? null : ['url' => \Chores\adresse_media($c->config, (int) $video['id'], (string) $video['fichier']), 'taille' => (int) $video['taille_o']];
}

function createur(\PDO $pdo, array $chanson): ?string
{
    if ($chanson['createur_id'] === null) {
        return null;
    }
    $requete = $pdo->prepare('SELECT prenom FROM cousins WHERE id = ?');
    $requete->execute([(int) $chanson['createur_id']]);
    $prenom = $requete->fetchColumn();

    return $prenom === false ? null : (string) $prenom;
}

/** POST /chanson/{slug}/section/{id}/niveau : niveau 0, 1 ou 2 pour moi ; redirection, ou JSON. */
function poser_niveau(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, $params['slug'] ?? '');
    $sectionId = preg_match('/^[1-9][0-9]{0,8}$/D', $params['id'] ?? '') ? (int) $params['id'] : 0;
    $sections = $chanson !== null ? sections_de($pdo, (int) $chanson['id']) : [];
    $index = array_search($sectionId, array_column($sections, 'id'), true);
    if ($chanson === null || $index === false) {
        return $c->veutJson() ? reponse_json(['erreur' => 'Section introuvable.'], 404) : page_introuvable($c);
    }
    if (ma_place_dans($c, distribution($pdo, (int) $chanson['id'])) === null) {
        $texte = 'Tu ne danses pas sur cette choré : ton niveau ne s’y marque pas.';

        return $c->veutJson() ? reponse_json(['erreur' => $texte], 403) : page_erreur($c->config, 403, 'Pas sur cette choré', $texte);
    }
    $niveau = $c->champ('niveau');
    if ($niveau === null || !in_array($niveau, ['0', '1', '2'], true)) {
        $texte = 'Niveau inconnu : choisis « Pas encore », « Presque » ou « Je maîtrise ».';

        return $c->veutJson() ? reponse_json(['erreur' => $texte], 422) : page_erreur($c->config, 422, 'Niveau inconnu', $texte);
    }
    if (!ecriture_autorisee($c)) {
        $refus = reponse_trop_d_essais($c, 'ecriture');

        return $c->veutJson() ? reponse_json(['erreur' => 'Trop de changements d’un coup. Réessaie un peu plus tard.'], 429)->avecEntete('Retry-After', (string) (3600 - time() % 3600)) : $refus;
    }
    $moi = (int) $c->cousin['id'];
    $avant = niveaux_du_cousin($pdo, $moi, (int) $chanson['id'])[$sectionId] ?? 0;
    $pdo->prepare(
        'INSERT INTO niveaux (cousin_id, section_id, niveau, maj_le) VALUES (?, ?, ?, ?)
         ON CONFLICT(cousin_id, section_id) DO UPDATE SET niveau = excluded.niveau, maj_le = excluded.maj_le WHERE niveaux.niveau <> excluded.niveau'
    )->execute([$moi, $sectionId, (int) $niveau, maintenant_utc()]);
    if ((int) $niveau !== $avant) {
        journaliser($pdo, 'niveau', $moi, $chanson['titre'] . ' · ' . $sections[$index]['nom'] . ' : ' . \Chores\NIVEAUX[(int) $niveau]);
    }
    if ($c->veutJson()) {
        [$maitrisees, $total] = progression_du_cousin($pdo, $moi, (int) $chanson['id']);

        return reponse_json([
            'section' => $sectionId,
            'niveau' => (int) $niveau,
            'libelle' => \Chores\NIVEAUX[(int) $niveau],
            'maitrisees' => $maitrisees,
            'total' => $total,
            'progression' => texte_progression($maitrisees, $total),
        ]);
    }

    return Reponse::redirection(url($c->config, '/chanson/' . $chanson['slug'] . '?section=' . ($index + 1) . '#section-' . $sectionId), 303);
}

/** POST /chanson/{slug}/createur : « qui s'en occupe » d'une choré à créer (un danseur, choisi par un danseur). */
function choisir_createur(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, $params['slug'] ?? '');
    if ($chanson === null || (int) $chanson['a_creer'] !== 1) {
        return page_introuvable($c);
    }
    $danseurs = array_filter(distribution($pdo, (int) $chanson['id']), static fn (array $d): bool => $d['statut'] === 'danse');
    if (!in_array((int) $c->cousin['id'], array_column($danseurs, 'cousin_id'), true)) {
        return page_erreur($c->config, 403, 'Pas sur cette choré', 'Seuls les danseurs de cette choré choisissent qui s’en occupe.');
    }
    $parPrenom = array_column($danseurs, 'cousin_id', 'prenom');
    $choisi = $parPrenom[(string) $c->champ('createur')] ?? null;
    if ($choisi === null) {
        return page_erreur($c->config, 422, 'Prénom inconnu', 'Choisis l’un des danseurs de cette choré.');
    }
    if (!ecriture_autorisee($c)) {
        return reponse_trop_d_essais($c, 'ecriture');
    }
    $pdo->prepare('UPDATE chansons SET createur_id = ? WHERE id = ?')->execute([$choisi, (int) $chanson['id']]);
    journaliser($pdo, 'createur', (int) $c->cousin['id'], $chanson['titre'] . ' : ' . array_search($choisi, $parPrenom, true));

    return Reponse::redirection(url($c->config, '/chanson/' . $chanson['slug'] . '?ok=createur#qui-s-en-occupe'), 303);
}

/** GET /chanson/{slug}/repeter?section=N : mode répétition sur les vraies sections. */
function repeter_chanson(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, $params['slug'] ?? '');
    if ($chanson === null) {
        return page_introuvable($c);
    }
    $sections = sections_de($pdo, (int) $chanson['id']);
    $courante = index_de_section($c, count($sections));

    return rendre($c, 'repeter', [
        'titre' => 'Répéter ' . $chanson['titre'],
        'themeForce' => 'sombre',
        'classePage' => 'page-repetition',
        'pied' => false,
        'scripts' => ['/js/lecteur.js'],
        'chanson' => chanson_pour_lecteur($chanson),
        'sections' => $sections,
        'courante' => $courante,
        'video' => video_de_reference($c, $chanson),
        'hrefQuitter' => url($c->config, '/chanson/' . $chanson['slug'] . '?section=' . ($courante + 1) . '#lecteur-chanson'),
        'hrefSection' => url($c->config, '/chanson/' . $chanson['slug'] . '/repeter?section='),
    ]);
}

/** GET /api/etat?chanson=slug : sections, mes niveaux, version du lien (pour le lecteur). */
function etat_json(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, (string) $c->parametre('chanson'));
    if ($chanson === null) {
        return reponse_json(['erreur' => 'Chanson introuvable.'], 404);
    }
    $niveaux = niveaux_du_cousin($pdo, (int) $c->cousin['id'], (int) $chanson['id']);

    return reponse_json([
        'chanson' => $chanson['slug'],
        'version' => version_lien($pdo),
        'sections' => array_map(static fn (array $s): array => [
            'id' => $s['id'], 'nom' => $s['nom'], 'debut' => $s['debut'], 'fin' => $s['fin'], 'comptes' => $s['comptes'],
            'niveau' => $niveaux[$s['id']] ?? 0,
        ], sections_de($pdo, (int) $chanson['id'])),
    ]);
}

/** GET /placements : la première de mes chorés à plusieurs, sinon la première à plusieurs. */
function placements_accueil(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $toutes = chansons_a_plusieurs($pdo);
    if ($toutes === []) {
        return rendre($c, 'placements', [
            'titre' => 'Placements', 'barre' => 'Chorés du mariage', 'barreAction' => 'retour', 'barreRetour' => url($c->config, '/moi'),
            'chanson' => null, 'chips' => [],
        ]);
    }
    $miennes = array_column(chansons_du_cousin($pdo, (int) $c->cousin['id']), 'slug');
    $choix = $toutes[0];
    foreach ($toutes as $chanson) {
        if (in_array($chanson['slug'], $miennes, true)) {
            $choix = $chanson;
            break;
        }
    }

    return Reponse::redirection(url($c->config, '/placements/' . $choix['slug']), 302);
}

/** GET /placements/{slug}?section=N[&scene=1] */
function page_placements(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $chanson = chanson_par_slug($pdo, $params['slug'] ?? '');
    $toutes = chansons_a_plusieurs($pdo);
    if ($chanson === null || !in_array($chanson['slug'], array_column($toutes, 'slug'), true)) {
        return page_introuvable($c);
    }
    $distribution = distribution($pdo, (int) $chanson['id']);
    $sections = sections_de($pdo, (int) $chanson['id']);
    $courante = index_de_section($c, count($sections));
    $scene = $c->parametre('scene') === '1';

    return rendre($c, 'placements', [
        'titre' => $scene ? 'Mode scène — ' . $chanson['titre'] : 'Placements — ' . $chanson['titre'],
        'barre' => $scene ? null : 'Chorés du mariage',
        'barreAction' => 'retour',
        'barreRetour' => url($c->config, '/moi'),
        'chanson' => $chanson,
        'chips' => $toutes,
        'distribution' => $distribution,
        'maPlace' => ma_place_dans($c, $distribution),
        'sections' => $sections,
        'courante' => $courante,
        'placements' => array_map(static fn (array $s): array => placements_section($pdo, $s['id']), $sections),
        'verrouilles' => reglage($pdo, 'placements_verrouilles') === '1',
        'scene' => $scene,
        'depuisJourJ' => $scene && $c->parametre('depuis') === 'jour-j',
        'surligner' => $c->parametre('surligner') !== '0',
        'confirmation' => $c->parametre('ok') === 'deplace' ? 'C’est enregistré : tout le monde voit ta nouvelle place.' : null,
    ]);
}

/** POST /placements/{section}/moi : un pas de 5 dans une direction pour mon point (vu par tous). */
function deplacer_mon_point(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    $sectionId = preg_match('/^[1-9][0-9]{0,8}$/D', $params['section'] ?? '') ? (int) $params['section'] : 0;
    $requete = $pdo->prepare('SELECT s.id, s.nom, s.chanson_id, ch.slug, ch.titre FROM sections s JOIN chansons ch ON ch.id = s.chanson_id WHERE s.id = ?');
    $requete->execute([$sectionId]);
    $section = $requete->fetch();
    if ($section === false) {
        return page_introuvable($c);
    }
    $index = array_search($sectionId, array_column(sections_de($pdo, (int) $section['chanson_id']), 'id'), true);
    $retour = '/placements/' . $section['slug'] . '?section=' . ((int) $index + 1) . ($c->champ('scene') === '1' ? '&scene=1' . ($c->champ('depuis') === 'jour-j' ? '&depuis=jour-j' : '') : '');
    if (ma_place_dans($c, distribution($pdo, (int) $section['chanson_id'])) === null) {
        return page_erreur($c->config, 403, 'Pas sur cette choré', 'Tu ne danses pas sur cette choré : ton point ne s’y déplace pas.');
    }
    if (reglage($pdo, 'placements_verrouilles') === '1') {
        return page_erreur($c->config, 403, 'Placements fixés', 'Les placements sont fixés : demande à Patrice pour un changement.');
    }
    $direction = (string) $c->champ('direction');
    if (!array_key_exists($direction, DIRECTIONS)) {
        return page_erreur($c->config, 422, 'Direction inconnue', 'Choisis gauche, droite, vers le fond ou vers le public.');
    }
    $moi = (int) $c->cousin['id'];
    $point = $pdo->prepare('SELECT x, y FROM placements WHERE section_id = ? AND cousin_id = ?');
    $point->execute([$sectionId, $moi]);
    $position = $point->fetch();
    if ($position === false) {
        return page_erreur($c->config, 409, 'Pas encore placé', 'Ta place sur cette section n’est pas encore posée : Patrice s’en charge.');
    }
    if (!ecriture_autorisee($c)) {
        return reponse_trop_d_essais($c, 'ecriture');
    }
    [$x, $y] = deplacer((int) $position['x'], (int) $position['y'], $direction);
    $pdo->prepare('UPDATE placements SET x = ?, y = ? WHERE section_id = ? AND cousin_id = ?')->execute([$x, $y, $sectionId, $moi]);
    journaliser($pdo, 'placement', $moi, sprintf('%s · %s : %s (%d, %d)', $section['titre'], $section['nom'], $direction, $x, $y));

    return Reponse::redirection(url($c->config, $retour . '&ok=deplace#scene'), 303);
}

/** GET /m/{id} : vidéo servie après contrôle du cookie d'accès (niveau de la route), plages d'octets. */
function servir_media(Contexte $c, array $params): Reponse
{
    $id = preg_match('/^[1-9][0-9]{0,8}$/D', $params['id'] ?? '') ? (int) $params['id'] : 0;
    $requete = $c->pdo()->prepare('SELECT fichier, mime FROM videos WHERE id = ? AND supprime_le IS NULL');
    $requete->execute([$id]);
    $video = $requete->fetch();
    if ($video === false || !preg_match('/^[0-9a-f]{32}\.[a-z0-9]{3,4}$/D', (string) $video['fichier'])) {
        return page_introuvable($c);
    }
    // Ancienne adresse d'une vidéo remplacée (?v= d'un autre fichier) : introuvable, pour qu'aucune copie gardée ne
    // survive (le service worker l'efface sur ce 404). Sans ?v= : le fichier actuel.
    $v = $c->parametre('v');
    if ($v !== null && $v !== substr((string) $video['fichier'], 0, 8)) {
        return page_introuvable($c);
    }

    return servir_fichier($c, \Chores\dossier_medias() . '/' . $video['fichier'], (string) $video['mime'], 'private, max-age=31536000, immutable');
}
