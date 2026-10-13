<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Lectures et règles du groupe (phase 5, spec § 2, 3, 7) : répétitions (réponses, constats), vidéos des cousins et
// commentaires, Jour J (ordre de passage, check-list), « Ce qui bouge ».

const REPONSES_PRESENCE = ['oui' => 'Je viens', 'non' => 'Je ne peux pas'];
const STATUTS_REPETITION = ['proposee' => 'proposée', 'decidee' => 'décidée', 'passee' => 'passée', 'annulee' => 'annulée'];
const CODECS = ['h264' => 'H.264', 'hevc' => 'HEVC', 'inconnu' => 'non reconnu'];
/** Mot posé sur une vidéo peut-être illisible ailleurs (HEVC ou codec non reconnu). */
const MOT_FORMAT_IPHONE = 'format iPhone : peut-être illisible ailleurs, Patrice la convertira';

// --- Répétitions -------------------------------------------------------------------------------------------

/** @return array<string, mixed>|null */
function repetition_par_id(PDO $pdo, string $id): ?array
{
    if (!preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
        return null;
    }
    $requete = $pdo->prepare('SELECT * FROM repetitions WHERE id = ?');
    $requete->execute([(int) $id]);
    $ligne = $requete->fetch();

    return $ligne === false ? null : $ligne;
}

/** On peut encore y répondre : décidée ou proposée, et pas encore passée. */
function repetition_ouverte(array $repetition, string $aujourdhui): bool
{
    return in_array($repetition['statut'], ['decidee', 'proposee'], true) && (string) $repetition['date'] >= $aujourdhui;
}

/** « oui », « non », « peut_etre » ou null. */
function ma_reponse(PDO $pdo, int $repetitionId, int $cousinId): ?string
{
    $requete = $pdo->prepare('SELECT reponse FROM presences WHERE repetition_id = ? AND cousin_id = ?');
    $requete->execute([$repetitionId, $cousinId]);
    $reponse = $requete->fetchColumn();

    return $reponse === false ? null : (string) $reponse;
}

/**
 * Pose ma réponse (source « site »). Renvoie true si elle a changé (journal), false si elle était déjà la même.
 */
function poser_presence(PDO $pdo, int $repetitionId, int $cousinId, string $reponse): bool
{
    if (!array_key_exists($reponse, REPONSES_PRESENCE)) {
        throw new \InvalidArgumentException('Réponse inconnue.');
    }
    $requete = $pdo->prepare('SELECT reponse, source FROM presences WHERE repetition_id = ? AND cousin_id = ?');
    $requete->execute([$repetitionId, $cousinId]);
    $ligne = $requete->fetch();
    $avant = $ligne === false ? null : (string) $ligne['reponse'];
    if ($avant === $reponse && $ligne['source'] === 'site') {
        return false;
    }
    $pdo->prepare(
        "INSERT INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES (?, ?, ?, 'site', ?)
         ON CONFLICT(repetition_id, cousin_id) DO UPDATE SET reponse = excluded.reponse, source = 'site', maj_le = excluded.maj_le"
    )->execute([$repetitionId, $cousinId, $reponse, maintenant_utc()]);

    return $avant !== $reponse;
}

/** @return array<int, int> cousin → 1 (présent) ou 0 (absent), constatés par Patrice */
function constats(PDO $pdo, int $repetitionId): array
{
    $requete = $pdo->prepare('SELECT cousin_id, present FROM constats WHERE repetition_id = ?');
    $requete->execute([$repetitionId]);
    $constats = [];
    foreach ($requete->fetchAll() as $l) {
        $constats[(int) $l['cousin_id']] = (int) $l['present'];
    }

    return $constats;
}

/**
 * Tout ce qu'une carte de répétition affiche : réponses par prénom, cousins sans réponse, constats par prénom.
 * @return array{oui: list<string>, non: list<string>, peut_etre: list<string>, sans_reponse: list<string>, presents: list<string>, absents: list<string>, pas_note: list<string>}
 */
function bilan_repetition(PDO $pdo, int $repetitionId): array
{
    $reponses = reponses_repetition($pdo, $repetitionId);
    $constats = constats($pdo, $repetitionId);
    $ont = array_merge($reponses['oui'], $reponses['non'], $reponses['peut_etre']);
    $bilan = $reponses + ['sans_reponse' => [], 'presents' => [], 'absents' => [], 'pas_note' => []];
    foreach (cousins_actifs($pdo) as $cousin) {
        if (!in_array($cousin['prenom'], $ont, true)) {
            $bilan['sans_reponse'][] = $cousin['prenom'];
        }
        $cle = match ($constats[$cousin['id']] ?? null) {
            1 => 'presents',
            0 => 'absents',
            default => 'pas_note',
        };
        $bilan[$cle][] = $cousin['prenom'];
    }

    return $bilan;
}

/** « samedi 17 octobre · 14 h » */
function quand_repetition(array $repetition, string $aujourdhui): string
{
    return date_en_mots((string) $repetition['date'], $aujourdhui) . ($repetition['heure'] !== null ? ' · ' . $repetition['heure'] : '');
}

// --- Vidéos des cousins ------------------------------------------------------------------------------------

/** Adresse du fichier : le nom interne entre dans l'adresse, une vidéo remplacée n'est jamais servie d'un cache. */
function adresse_media(array $config, int $id, string $fichier): string
{
    return url($config, '/m/' . $id) . '?v=' . substr($fichier, 0, 8);
}

/**
 * Vidéos des cousins non supprimées, avec auteur et chanson ; toutes, ou celles d'une chanson.
 * @return list<array<string, mixed>>
 */
function videos_des_cousins(PDO $pdo, ?int $chansonId = null): array
{
    $sql = "SELECT v.*, c.prenom AS auteur, ch.titre AS chanson_titre, ch.slug AS chanson_slug, ch.ordre_passage
            FROM videos v JOIN chansons ch ON ch.id = v.chanson_id LEFT JOIN cousins c ON c.id = v.cousin_id
            WHERE v.type = 'cousin' AND v.supprime_le IS NULL" . ($chansonId !== null ? ' AND v.chanson_id = ?' : '') . '
            ORDER BY ch.ordre_passage IS NULL, ch.ordre_passage, ch.titre COLLATE NOCASE, v.cree_le DESC, v.id DESC';
    $requete = $pdo->prepare($sql);
    $requete->execute($chansonId !== null ? [$chansonId] : []);

    return $requete->fetchAll();
}

/** Vidéo d'un cousin non supprimée (avec auteur et chanson), ou null. @return array<string, mixed>|null */
function video_de_cousin(PDO $pdo, string $id): ?array
{
    if (!preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
        return null;
    }
    $requete = $pdo->prepare(
        "SELECT v.*, c.prenom AS auteur, ch.titre AS chanson_titre, ch.slug AS chanson_slug
         FROM videos v JOIN chansons ch ON ch.id = v.chanson_id LEFT JOIN cousins c ON c.id = v.cousin_id
         WHERE v.id = ? AND v.type = 'cousin' AND v.supprime_le IS NULL"
    );
    $requete->execute([(int) $id]);
    $ligne = $requete->fetch();

    return $ligne === false ? null : $ligne;
}

/**
 * Commentaires visibles d'une vidéo : par instant (deux au même instant : par date d'écriture), les commentaires
 * généraux à la fin.
 * @return list<array{id: int, cousin_id: ?int, auteur: string, temps: ?float, texte: string, cree_le: string}>
 */
function commentaires_de(PDO $pdo, int $videoId): array
{
    $requete = $pdo->prepare(
        'SELECT m.id, m.cousin_id, c.prenom, m.temps_s, m.texte, m.cree_le FROM commentaires m LEFT JOIN cousins c ON c.id = m.cousin_id
         WHERE m.video_id = ? AND m.supprime_le IS NULL ORDER BY m.temps_s IS NULL, m.temps_s, m.cree_le, m.id'
    );
    $requete->execute([$videoId]);

    return array_map(static fn (array $l): array => [
        'id' => (int) $l['id'],
        'cousin_id' => $l['cousin_id'] !== null ? (int) $l['cousin_id'] : null,
        'auteur' => (string) ($l['prenom'] ?? 'un cousin'),
        'temps' => $l['temps_s'] !== null ? (float) $l['temps_s'] : null,
        'texte' => (string) $l['texte'],
        'cree_le' => (string) $l['cree_le'],
    ], $requete->fetchAll());
}

/**
 * Marque une vidéo supprimée et renvoie le nom de son fichier tel qu'il est à cet instant (lu dans la même requête :
 * un remplacement qui se croise avec la suppression ne laisse pas de fichier orphelin), ou null si elle l'était déjà.
 */
function marquer_video_supprimee(PDO $pdo, int $id): ?string
{
    // Lecture et marquage dans une même transaction d'écriture (pas de « RETURNING » : il faut SQLite 3.35, et la
    // version de l'hébergeur n'est pas connue). Dans une transaction déjà ouverte par l'appelant, on s'y joint.
    $propre = !$pdo->inTransaction();
    if ($propre) {
        $pdo->exec('BEGIN IMMEDIATE');
    }
    try {
        $lecture = $pdo->prepare('SELECT fichier FROM videos WHERE id = ? AND supprime_le IS NULL');
        $lecture->execute([$id]);
        $fichier = $lecture->fetchColumn();
        if ($fichier !== false) {
            $pdo->prepare('UPDATE videos SET supprime_le = ? WHERE id = ?')->execute([maintenant_utc(), $id]);
        }
        if ($propre) {
            $pdo->exec('COMMIT');
        }
    } catch (\Throwable $erreur) {
        if ($propre) {
            $pdo->exec('ROLLBACK');
        }
        throw $erreur;
    }

    return $fichier === false ? null : (string) $fichier;
}

/** HEVC ou codec non reconnu : peut-être illisible sur Android et PC. */
function video_peut_etre_illisible(array $video): bool
{
    return in_array($video['codec_devine'], ['hevc', 'inconnu'], true) && $video['mime'] !== 'video/webm';
}

/** « 0:42 », « 1:02:05 » ou null (illisible ou vide). Accepte aussi « 42 » (secondes). */
function lire_instant(?string $texte): ?float
{
    $temps = lire_temps($texte);

    return $temps !== null && $temps <= 36000 ? $temps : null;
}

// --- Jour J ------------------------------------------------------------------------------------------------

/** @return list<array<string, mixed>> chansons qui ont un ordre de passage, hors écartées */
function ordre_de_passage(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM chansons WHERE ordre_passage IS NOT NULL AND statut <> 'ecartee' ORDER BY ordre_passage, titre COLLATE NOCASE"
    )->fetchAll();
}

/** Durée affichée dans la frise : coupée si connue, sinon entière, sinon « [durée inconnue] ». */
function duree_jour_j(array $chanson): string
{
    if ($chanson['duree_coupee_s'] !== null) {
        return format_duree((int) $chanson['duree_coupee_s']);
    }

    return $chanson['duree_s'] !== null ? format_duree((int) $chanson['duree_s']) . ' (chanson entière)' : '[durée inconnue]';
}

/** @return list<array{id: int, libelle: string, ordre: int, fait: bool, par: ?string, fait_le: ?string}> */
function checklist(PDO $pdo): array
{
    return array_map(static fn (array $l): array => [
        'id' => (int) $l['id'],
        'libelle' => (string) $l['libelle'],
        'ordre' => (int) $l['ordre'],
        'fait' => (int) $l['fait'] === 1,
        'par' => $l['prenom'] !== null ? (string) $l['prenom'] : null,
        'fait_le' => $l['fait_le'] !== null ? (string) $l['fait_le'] : null,
    ], $pdo->query('SELECT k.*, c.prenom FROM checklist k LEFT JOIN cousins c ON c.id = k.fait_par ORDER BY k.ordre, k.id')->fetchAll());
}

/** @return array<string, mixed>|null */
function ligne_checklist(PDO $pdo, string $id): ?array
{
    if (!preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
        return null;
    }
    $requete = $pdo->prepare('SELECT * FROM checklist WHERE id = ?');
    $requete->execute([(int) $id]);
    $ligne = $requete->fetch();

    return $ligne === false ? null : $ligne;
}

// --- Ce qui bouge ------------------------------------------------------------------------------------------

/**
 * Les derniers événements du groupe : vidéos, commentaires, présences données sur le site, niveaux « je maîtrise ».
 * @return list<array{vignette: string, titre: string, detail: string, chemin: string, quand: string}>
 */
function ce_qui_bouge(PDO $pdo, int $nombre = 10, ?string $aujourdhui = null): array
{
    $aujourdhui ??= aujourdhui_paris();
    $evenements = [];

    $niveaux = $pdo->prepare(
        'SELECT c.prenom, s.nom, ch.titre, ch.slug, n.maj_le,
                (SELECT COUNT(*) FROM sections s2 WHERE s2.chanson_id = s.chanson_id AND (s2.ordre < s.ordre OR (s2.ordre = s.ordre AND s2.debut_s < s.debut_s))) AS rang
         FROM niveaux n JOIN sections s ON s.id = n.section_id JOIN chansons ch ON ch.id = s.chanson_id JOIN cousins c ON c.id = n.cousin_id
         JOIN distributions d ON d.chanson_id = s.chanson_id AND d.cousin_id = n.cousin_id
         WHERE n.niveau = 2 AND c.actif = 1 ORDER BY n.maj_le DESC, n.rowid DESC LIMIT ?'
    );
    $niveaux->bindValue(1, $nombre, PDO::PARAM_INT);
    $niveaux->execute();
    foreach ($niveaux->fetchAll() as $l) {
        $evenements[] = [
            'vignette' => (string) $l['titre'],
            'titre' => $l['prenom'] . ' maîtrise ' . $l['nom'],
            'detail' => $l['titre'] . ' · ' . date_paris((string) $l['maj_le']),
            'chemin' => '/chanson/' . $l['slug'] . '?section=' . ((int) $l['rang'] + 1),
            'quand' => (string) $l['maj_le'],
        ];
    }

    $videos = $pdo->prepare(
        "SELECT v.id, v.titre, v.cree_le, c.prenom, ch.titre AS chanson FROM videos v JOIN chansons ch ON ch.id = v.chanson_id
         LEFT JOIN cousins c ON c.id = v.cousin_id WHERE v.type = 'cousin' AND v.supprime_le IS NULL AND (c.id IS NULL OR c.actif = 1) ORDER BY v.cree_le DESC, v.id DESC LIMIT ?"
    );
    $videos->bindValue(1, $nombre, PDO::PARAM_INT);
    $videos->execute();
    foreach ($videos->fetchAll() as $l) {
        $evenements[] = [
            'vignette' => (string) $l['chanson'],
            'titre' => ($l['prenom'] ?? 'Un cousin') . ' a posté « ' . $l['titre'] . ' »',
            'detail' => $l['chanson'] . ' · ' . date_paris((string) $l['cree_le']),
            'chemin' => '/videos/' . (int) $l['id'],
            'quand' => (string) $l['cree_le'],
        ];
    }

    $commentaires = $pdo->prepare(
        "SELECT m.temps_s, m.texte, m.cree_le, c.prenom, v.id AS video, a.prenom AS auteur, ch.titre AS chanson
         FROM commentaires m JOIN videos v ON v.id = m.video_id JOIN chansons ch ON ch.id = v.chanson_id
         LEFT JOIN cousins c ON c.id = m.cousin_id LEFT JOIN cousins a ON a.id = v.cousin_id
         WHERE m.supprime_le IS NULL AND v.supprime_le IS NULL AND v.type = 'cousin' AND (c.id IS NULL OR c.actif = 1) ORDER BY m.cree_le DESC, m.id DESC LIMIT ?"
    );
    $commentaires->bindValue(1, $nombre, PDO::PARAM_INT);
    $commentaires->execute();
    foreach ($commentaires->fetchAll() as $l) {
        $temps = $l['temps_s'] !== null ? (float) $l['temps_s'] : null;
        $evenements[] = [
            'vignette' => (string) $l['chanson'],
            'titre' => ($l['prenom'] ?? 'Un cousin') . ($temps !== null ? ' à ' . format_temps($temps) : '') . ' : « ' . $l['texte'] . ' »',
            'detail' => 'sur la vidéo ' . de($l['auteur'] ?? 'un cousin') . ' · ' . $l['chanson'],
            'chemin' => '/videos/' . (int) $l['video'] . ($temps !== null ? '?t=' . (int) floor($temps) : ''),
            'quand' => (string) $l['cree_le'],
        ];
    }

    $presences = $pdo->prepare(
        "SELECT p.reponse, p.maj_le, c.prenom, r.date FROM presences p JOIN cousins c ON c.id = p.cousin_id
         JOIN repetitions r ON r.id = p.repetition_id
         WHERE p.source = 'site' AND p.reponse IN ('oui', 'non') AND c.actif = 1 AND r.statut <> 'annulee' ORDER BY p.maj_le DESC LIMIT ?"
    );
    $presences->bindValue(1, $nombre, PDO::PARAM_INT);
    $presences->execute();
    foreach ($presences->fetchAll() as $l) {
        $jour = date_en_mots((string) $l['date'], $aujourdhui);
        $evenements[] = [
            'vignette' => 'Répétition',
            'titre' => $l['prenom'] . ($l['reponse'] === 'oui' ? ' vient ' : ' ne peut pas venir ') . $jour,
            'detail' => 'Répétitions · ' . date_paris((string) $l['maj_le']),
            'chemin' => '/repetitions',
            'quand' => (string) $l['maj_le'],
        ];
    }

    usort($evenements, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

    return array_slice($evenements, 0, $nombre);
}
