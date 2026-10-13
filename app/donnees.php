<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Lectures et règles des données (spec § 3) : chansons, distributions, sections, niveaux, placements, liens,
// répétitions, « Ce qui bouge », espace de stockage ; lecture des temps « mm:ss,d » et validation des sections.

const NIVEAUX = ['Pas encore', 'Presque', 'Je maîtrise'];
const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** Date du jour à Paris, AAAA-MM-JJ. */
function aujourdhui_paris(?int $horodatage = null): string
{
    return (new \DateTimeImmutable('@' . ($horodatage ?? time())))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
}

/** « 2026-10-17 » → « samedi 17 octobre » (l'année si ce n'est pas celle d'aujourd'hui). */
function date_en_mots(string $date, ?string $aujourdhui = null): string
{
    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Europe/Paris'));
    if ($d === false) {
        return $date;
    }
    $annee = substr($aujourdhui ?? aujourdhui_paris(), 0, 4) === $d->format('Y') ? '' : ' ' . $d->format('Y');

    return JOURS[(int) $d->format('w')] . ' ' . ((int) $d->format('j') === 1 ? '1er' : $d->format('j')) . ' ' . MOIS[(int) $d->format('n') - 1] . $annee;
}

// --- Chansons ----------------------------------------------------------------------------------------------

/** @return array<string, mixed>|null */
function chanson_par_slug(PDO $pdo, string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,60}$/D', $slug)) {
        return null;
    }
    $requete = $pdo->prepare('SELECT * FROM chansons WHERE slug = ?');
    $requete->execute([$slug]);
    $ligne = $requete->fetch();

    return $ligne === false ? null : $ligne;
}

/** @return list<array<string, mixed>> toutes les chansons, ordre de passage puis titre */
function toutes_les_chansons(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM chansons ORDER BY ordre_passage IS NULL, ordre_passage, titre COLLATE NOCASE')->fetchAll();
}

/**
 * Chansons où le cousin danse ou est en attente (hors chansons écartées), ordre de passage puis titre.
 * @return list<array<string, mixed>> lignes de chansons + « ma_place » (danse ou en_attente)
 */
function chansons_du_cousin(PDO $pdo, int $cousinId): array
{
    $requete = $pdo->prepare(
        "SELECT ch.*, d.statut AS ma_place FROM chansons ch JOIN distributions d ON d.chanson_id = ch.id
         WHERE d.cousin_id = ? AND ch.statut <> 'ecartee'
         ORDER BY ch.ordre_passage IS NULL, ch.ordre_passage, ch.titre COLLATE NOCASE"
    );
    $requete->execute([$cousinId]);

    return $requete->fetchAll();
}

/** @return list<array<string, mixed>> chansons proposées sans aucun danseur (« Proposées, pas tranchées ») */
function chansons_proposees_sans_danseur(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM chansons ch WHERE statut = 'proposee'
         AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.chanson_id = ch.id)
         ORDER BY titre COLLATE NOCASE"
    )->fetchAll();
}

/** @return list<array<string, mixed>> chansons à au moins deux danseurs (placements), hors écartées */
function chansons_a_plusieurs(PDO $pdo): array
{
    return $pdo->query(
        "SELECT ch.* FROM chansons ch WHERE ch.statut <> 'ecartee'
         AND (SELECT COUNT(*) FROM distributions d JOIN cousins c ON c.id = d.cousin_id WHERE d.chanson_id = ch.id AND c.actif = 1) >= 2
         ORDER BY ch.ordre_passage IS NULL, ch.ordre_passage, ch.titre COLLATE NOCASE"
    )->fetchAll();
}

/** @return list<array{cousin_id: int, prenom: string, statut: string}> par ordre des cousins */
function distribution(PDO $pdo, int $chansonId): array
{
    $requete = $pdo->prepare(
        'SELECT d.cousin_id, c.prenom, d.statut FROM distributions d JOIN cousins c ON c.id = d.cousin_id
         WHERE d.chanson_id = ? AND c.actif = 1 ORDER BY c.ordre'
    );
    $requete->execute([$chansonId]);

    return array_map(static fn (array $l): array => [
        'cousin_id' => (int) $l['cousin_id'], 'prenom' => (string) $l['prenom'], 'statut' => (string) $l['statut'],
    ], $requete->fetchAll());
}

/** « Alex, Fabien, Flobert », « solo », « 8 danseurs » ; « Patrice en attente » ajouté. */
function texte_danseurs(array $distribution): string
{
    $danse = array_column(array_filter($distribution, static fn (array $d): bool => $d['statut'] === 'danse'), 'prenom');
    $attente = array_column(array_filter($distribution, static fn (array $d): bool => $d['statut'] === 'en_attente'), 'prenom');
    $texte = match (true) {
        $danse === [] => 'pas encore de danseur',
        count($danse) === 1 => 'solo de ' . $danse[0],
        count($danse) <= 4 => implode(', ', $danse),
        default => count($danse) . ' danseurs',
    };
    if ($attente !== []) {
        $texte .= ' · ' . implode(', ', $attente) . ' en attente';
    }

    return $texte;
}

/** Film, durée et tempo tels que la page les écrit, avec les crochets des valeurs inconnues. */
function infos_chanson(array $chanson): array
{
    $bpm = $chanson['bpm'] !== null ? (int) $chanson['bpm'] : null;

    return [
        'film' => $chanson['film'] !== null && $chanson['film'] !== '' ? (string) $chanson['film'] : '[film à confirmer]',
        'duree' => $chanson['duree_s'] !== null ? format_duree((int) $chanson['duree_s']) : '[durée inconnue]',
        'tempo' => $bpm === null ? 'tempo à régler' : ((int) $chanson['bpm_verifie'] === 1 ? $bpm . ' tpm' : 'tempo à confirmer (' . $bpm . ' tpm ?)'),
    ];
}

/** Chanson telle que le lecteur l'attend. */
function chanson_pour_lecteur(array $chanson): array
{
    return [
        'cle' => (string) $chanson['slug'],
        'titre' => (string) $chanson['titre'],
        'duree_s' => $chanson['duree_s'] !== null ? (int) $chanson['duree_s'] : null,
        'bpm' => $chanson['bpm'] !== null ? (int) $chanson['bpm'] : null,
    ];
}

// --- Sections et niveaux -----------------------------------------------------------------------------------

/** @return list<array{id: int, ordre: int, nom: string, debut: float, fin: float, comptes: int, note: ?string}> */
function sections_de(PDO $pdo, int $chansonId): array
{
    $requete = $pdo->prepare('SELECT id, ordre, nom, debut_s, fin_s, comptes, note FROM sections WHERE chanson_id = ? ORDER BY ordre, debut_s');
    $requete->execute([$chansonId]);

    return array_map(static fn (array $l): array => [
        'id' => (int) $l['id'], 'ordre' => (int) $l['ordre'], 'nom' => (string) $l['nom'],
        'debut' => (float) $l['debut_s'], 'fin' => (float) $l['fin_s'], 'comptes' => (int) $l['comptes'],
        'note' => $l['note'] !== null ? (string) $l['note'] : null,
    ], $requete->fetchAll());
}

/** @return array<int, int> id de section → niveau (absence = 0) */
function niveaux_du_cousin(PDO $pdo, int $cousinId, int $chansonId): array
{
    $requete = $pdo->prepare(
        'SELECT n.section_id, n.niveau FROM niveaux n JOIN sections s ON s.id = n.section_id WHERE n.cousin_id = ? AND s.chanson_id = ?'
    );
    $requete->execute([$cousinId, $chansonId]);
    $niveaux = [];
    foreach ($requete->fetchAll() as $l) {
        $niveaux[(int) $l['section_id']] = (int) $l['niveau'];
    }

    return $niveaux;
}

/**
 * Grille « Qui maîtrise quoi » : danseurs puis en attente, niveaux dans l'ordre des sections.
 * @param list<array{id: int}> $sections
 * @return list<array{prenom: string, attente: bool, niveaux: list<int>}>
 */
function grille_maitrise(PDO $pdo, int $chansonId, array $sections, array $distribution): array
{
    $requete = $pdo->prepare('SELECT n.cousin_id, n.section_id, n.niveau FROM niveaux n JOIN sections s ON s.id = n.section_id WHERE s.chanson_id = ?');
    $requete->execute([$chansonId]);
    $par = [];
    foreach ($requete->fetchAll() as $l) {
        $par[(int) $l['cousin_id']][(int) $l['section_id']] = (int) $l['niveau'];
    }
    usort($distribution, static fn (array $a, array $b): int => ($a['statut'] === 'en_attente') <=> ($b['statut'] === 'en_attente'));

    return array_map(static fn (array $d): array => [
        'prenom' => $d['prenom'],
        'attente' => $d['statut'] === 'en_attente',
        'niveaux' => array_map(static fn (array $s): int => $par[$d['cousin_id']][$s['id']] ?? 0, $sections),
    ], $distribution);
}

/** @return array{0: int, 1: int} [sections maîtrisées, nombre de sections] */
function progression_du_cousin(PDO $pdo, int $cousinId, int $chansonId): array
{
    $requete = $pdo->prepare(
        'SELECT COUNT(*) AS total, SUM(CASE WHEN n.niveau = 2 THEN 1 ELSE 0 END) AS maitrisees
         FROM sections s LEFT JOIN niveaux n ON n.section_id = s.id AND n.cousin_id = ? WHERE s.chanson_id = ?'
    );
    $requete->execute([$cousinId, $chansonId]);
    $l = $requete->fetch();

    return [(int) ($l['maitrisees'] ?? 0), (int) ($l['total'] ?? 0)];
}

/** « rien de marqué encore », « 1 section sur 2 maîtrisée », « 2 sections sur 3 maîtrisées ». */
function texte_progression(int $maitrisees, int $total): string
{
    if ($maitrisees === 0) {
        return 'rien de marqué encore';
    }

    return sprintf('%d section%s sur %d maîtrisée%s', $maitrisees, $maitrisees > 1 ? 's' : '', $total, $maitrisees > 1 ? 's' : '');
}

// --- Placements --------------------------------------------------------------------------------------------

/** @return list<array{cousin_id: int, prenom: string, x: int, y: int}> */
function placements_section(PDO $pdo, int $sectionId): array
{
    // Seuls les danseurs actuels de la chanson (un cousin retiré n'a plus de point).
    $requete = $pdo->prepare(
        'SELECT p.cousin_id, c.prenom, p.x, p.y FROM placements p JOIN cousins c ON c.id = p.cousin_id
         JOIN sections s ON s.id = p.section_id JOIN distributions d ON d.chanson_id = s.chanson_id AND d.cousin_id = p.cousin_id
         WHERE p.section_id = ? AND c.actif = 1 ORDER BY c.ordre'
    );
    $requete->execute([$sectionId]);

    return array_map(static fn (array $l): array => [
        'cousin_id' => (int) $l['cousin_id'], 'prenom' => (string) $l['prenom'], 'x' => (int) $l['x'], 'y' => (int) $l['y'],
    ], $requete->fetchAll());
}

/** Prénoms de la distribution qui n'ont pas de point sur la section. */
function non_places(array $distribution, array $points): array
{
    $places = array_column($points, 'prenom');

    return array_values(array_diff(array_column($distribution, 'prenom'), $places));
}

const PAS_PLACEMENT = 5;
const DIRECTIONS = ['gauche' => [-1, 0], 'droite' => [1, 0], 'fond' => [0, -1], 'public' => [0, 1]];

/** Nouvelle position après un pas dans une direction, bornée à la scène. @return array{0: int, 1: int} */
function deplacer(int $x, int $y, string $direction): array
{
    [$dx, $dy] = DIRECTIONS[$direction] ?? [0, 0];

    return [max(0, min(100, $x + $dx * PAS_PLACEMENT)), max(0, min(100, $y + $dy * PAS_PLACEMENT))];
}

// --- Liens ---------------------------------------------------------------------------------------------------

/** @return list<array<string, mixed>> références partagées puis officiels puis tutoriels */
function liens_de(PDO $pdo, int $chansonId): array
{
    $requete = $pdo->prepare(
        "SELECT * FROM liens WHERE chanson_id = ? ORDER BY CASE type WHEN 'reference' THEN 0 WHEN 'officiel' THEN 1 ELSE 2 END, id"
    );
    $requete->execute([$chansonId]);

    return $requete->fetchAll();
}

const TYPES_LIENS = ['reference' => 'référence partagée dans le groupe', 'officiel' => 'officiel', 'tutoriel' => 'tutoriel'];

// --- Répétitions -------------------------------------------------------------------------------------------

/**
 * Prochaine répétition pour Ma page : la prochaine décidée (aujourd'hui ou après), sinon la proposée à venir la plus
 * votée (égalité : la plus proche), sinon null.
 * @return array{repetition: array<string, mixed>, fixee: bool, dispos: int, viennent: list<string>, nePeuventPas: list<string>}|null
 */
function prochaine_repetition(PDO $pdo, string $aujourdhui): ?array
{
    $decidee = $pdo->prepare("SELECT * FROM repetitions WHERE statut = 'decidee' AND date >= ? ORDER BY date LIMIT 1");
    $decidee->execute([$aujourdhui]);
    $r = $decidee->fetch();
    $fixee = $r !== false;
    if (!$fixee) {
        $proposee = $pdo->prepare(
            "SELECT r.*, (SELECT COUNT(*) FROM presences p WHERE p.repetition_id = r.id AND p.reponse IN ('oui', 'peut_etre')) AS dispos
             FROM repetitions r WHERE r.statut = 'proposee' AND r.date >= ? ORDER BY dispos DESC, r.date LIMIT 1"
        );
        $proposee->execute([$aujourdhui]);
        $r = $proposee->fetch();
        if ($r === false) {
            return null;
        }
    }
    $reponses = reponses_repetition($pdo, (int) $r['id']);

    return [
        'repetition' => $r,
        'fixee' => $fixee,
        'dispos' => count($reponses['oui']) + count($reponses['peut_etre']),
        'viennent' => $reponses['oui'],
        'nePeuventPas' => $reponses['non'],
    ];
}

/** @return array{oui: list<string>, non: list<string>, peut_etre: list<string>} prénoms par réponse */
function reponses_repetition(PDO $pdo, int $repetitionId): array
{
    $requete = $pdo->prepare(
        'SELECT c.prenom, p.reponse FROM presences p JOIN cousins c ON c.id = p.cousin_id WHERE p.repetition_id = ? ORDER BY c.ordre'
    );
    $requete->execute([$repetitionId]);
    $reponses = ['oui' => [], 'non' => [], 'peut_etre' => []];
    foreach ($requete->fetchAll() as $l) {
        $reponses[(string) $l['reponse']][] = (string) $l['prenom'];
    }

    return $reponses;
}

/** @return list<array<string, mixed>> toutes les répétitions, de la plus proche à la plus lointaine */
function repetitions(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM repetitions ORDER BY date')->fetchAll();
}

// --- Temps des sections --------------------------------------------------------------------------------------

/** « 0:17,5 », « 1:02 », « 17 », « 17.5 », « 1:02:05,3 » → secondes ; null si illisible. */
function lire_temps(?string $texte): ?float
{
    $texte = str_replace(',', '.', trim((string) $texte));
    if (!preg_match('/^(?:(?:(\d{1,2}):)?(\d{1,3}):)?(\d{1,4}(?:\.\d{1,3})?)$/D', $texte, $m) || $texte === '') {
        return null;
    }
    $secondes = (float) $m[3];
    // Secondes sous 60 dès qu'il y a des minutes ; minutes sous 60 dès qu'il y a des heures.
    if ((($m[2] ?? '') !== '' && $secondes >= 60) || (($m[1] ?? '') !== '' && (int) $m[2] >= 60)) {
        return null;
    }

    return round((int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + $secondes, 3);
}

/** 17.5 → « 0:17,5 » ; 62 → « 1:02,0 ». */
function format_temps_precis(float $secondes): string
{
    $dixiemes = (int) round($secondes * 10);

    return sprintf('%d:%02d,%d', intdiv($dixiemes, 600), intdiv($dixiemes % 600, 10), $dixiemes % 10);
}

/**
 * Valide une section avant enregistrement (spec § 3) : nom 1 à 40 caractères, début et fin lisibles, fin > début,
 * fin ≤ durée si connue, comptes 1 à 99, note 200 caractères, aucun chevauchement avec les autres sections.
 * @param array{nom?: ?string, debut?: ?string, fin?: ?string, comptes?: ?string, note?: ?string} $champs
 * @return array{erreurs: array<string, string>, valeurs: array{nom: string, debut: ?float, fin: ?float, comptes: int, note: ?string}}
 */
function valider_section(PDO $pdo, array $chanson, array $champs, ?int $sectionId = null): array
{
    $erreurs = [];
    $nom = trim((string) ($champs['nom'] ?? ''));
    $note = trim((string) ($champs['note'] ?? ''));
    $debut = lire_temps($champs['debut'] ?? null);
    $fin = lire_temps($champs['fin'] ?? null);
    $comptesTexte = trim((string) ($champs['comptes'] ?? ''));
    $comptes = preg_match('/^\d{1,2}$/D', $comptesTexte) ? (int) $comptesTexte : 0;

    if ($nom === '' || mb_strlen($nom) > 40) {
        $erreurs['nom'] = 'Le nom est vide ou trop long : écris de 1 à 40 caractères.';
    }
    if ($debut === null) {
        $erreurs['debut'] = 'Le début est illisible : écris-le en minutes et secondes, par exemple 0:17,5.';
    }
    if ($fin === null) {
        $erreurs['fin'] = 'La fin est illisible : écris-la en minutes et secondes, par exemple 0:25.';
    }
    if ($debut !== null && $fin !== null && $fin <= $debut) {
        $erreurs['fin'] = 'La fin doit venir après le début.';
    }
    if ($fin !== null && $chanson['duree_s'] !== null && $fin > (float) $chanson['duree_s'] + 0.05) {
        $erreurs['fin'] = 'La fin dépasse la durée de la chanson (' . format_temps((int) $chanson['duree_s']) . ').';
    }
    if ($comptes < 1 || $comptes > 99) {
        $erreurs['comptes'] = 'Le nombre de comptes de 8 va de 1 à 99.';
    }
    if (mb_strlen($note) > 200) {
        $erreurs['note'] = 'La note dépasse 200 caractères.';
    }
    if (!isset($erreurs['debut']) && !isset($erreurs['fin']) && $debut !== null && $fin !== null) {
        $autres = $pdo->prepare('SELECT nom, debut_s, fin_s FROM sections WHERE chanson_id = ? AND id <> ? AND ? < fin_s AND ? > debut_s ORDER BY debut_s LIMIT 1');
        $autres->execute([(int) $chanson['id'], $sectionId ?? 0, $debut, $fin]);
        $chevauche = $autres->fetch();
        if ($chevauche !== false) {
            $erreurs['debut'] = sprintf(
                'Cette section chevauche « %s » (%s à %s) : deux sections ne se recouvrent jamais.',
                $chevauche['nom'],
                format_temps_precis((float) $chevauche['debut_s']),
                format_temps_precis((float) $chevauche['fin_s'])
            );
        }
    }

    return ['erreurs' => $erreurs, 'valeurs' => ['nom' => $nom, 'debut' => $debut, 'fin' => $fin, 'comptes' => $comptes, 'note' => $note === '' ? null : $note]];
}

// --- Stockage ------------------------------------------------------------------------------------------------

/** Dossier des vidéos (hors racine web). Les tests le remplacent par un dossier temporaire. */
function dossier_medias(?string $remplacer = null): string
{
    static $dossier = null;
    if ($remplacer !== null) {
        $dossier = rtrim($remplacer, '/');
    }

    return $dossier ?? RACINE . '/stockage/medias';
}

/** Octets utilisés par les vidéos (non supprimées) d'après la base. */
function espace_videos(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(SUM(taille_o), 0) FROM videos WHERE supprime_le IS NULL')->fetchColumn();
}

/** Octets du dossier de stockage (base, vidéos, sauvegardes), mesurés sur le disque. */
function taille_dossier(string $dossier): int
{
    if (!is_dir($dossier)) {
        return 0;
    }
    $total = 0;
    $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterateur as $fichier) {
        if ($fichier instanceof \SplFileInfo && $fichier->isFile()) {
            $total += $fichier->getSize();
        }
    }

    return $total;
}

/** 1536 → « 1,5 Ko » ; 73400320 → « 70 Mo ». */
function format_octets(int $octets): string
{
    $unites = ['o', 'Ko', 'Mo', 'Go'];
    $valeur = (float) $octets;
    $i = 0;
    while ($valeur >= 1024 && $i < 3) {
        $valeur /= 1024;
        ++$i;
    }
    $texte = $i === 0 || $valeur >= 10 ? (string) round($valeur) : str_replace('.', ',', (string) round($valeur, 1));

    return $texte . ' ' . $unites[$i];
}
