<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Maintenance (spec § 10) : installation, migrations en attente, sauvegarde de la base (VACUUM INTO, ou copie
// après un point de contrôle si SQLite est trop ancien), restauration contrôlée sous un témoin de maintenance.
// Utilisée par les scripts (sauvegarde.php, restaurer.php), par l'installation sans SSH et par l'administration.

/** Témoin posé pendant une restauration : le site répond « maintenance » (503) sans ouvrir la base. */
const TEMOIN_MAINTENANCE = 'maintenance.actif';
/** Contenu du témoin posé par restaurer_base() : oublié au bout de 10 minutes (un témoin vide, posé à la main, non). */
const MARQUE_TEMOIN_AUTOMATIQUE = 'Restauration en cours.';

/** La base contient-elle déjà les tables du site (au moins la première migration) ? */
function base_installee(PDO $pdo): bool
{
    return $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'migrations'")->fetchColumn() !== false
        && (bool) $pdo->query("SELECT 1 FROM migrations WHERE version LIKE '001-%'")->fetchColumn();
}

/** Installation sans SSH encore possible : une clé d'administration configurée et aucun lien créé. */
function installation_possible(Contexte $c): bool
{
    if ($c->config['CLE_ADMIN'] === '') {
        return false;
    }
    $pdo = $c->pdo();

    return !base_installee($pdo) || version_lien($pdo) === 0;
}

/** @return list<string> versions des migrations présentes dans app/migrations/ et pas encore appliquées */
function migrations_en_attente(PDO $pdo, ?string $dossier = null): array
{
    $deja = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'migrations'")->fetchColumn() !== false
        ? $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN)
        : [];

    return array_values(array_diff(array_keys(fichiers_migrations($dossier)), $deja));
}

/**
 * Fichiers des faits du groupe à importer : docs/ dans le dépôt, donnees/ dans le paquet de mise en ligne.
 * @return array{0: string, 1: string}|null [etat.json, distribution.md], ou null s'ils manquent
 */
function fichiers_des_faits(?string $racine = null): ?array
{
    $racine ??= RACINE;
    foreach ([['/docs/whatsapp/etat.json', '/docs/distribution.md'], ['/donnees/etat.json', '/donnees/distribution.md']] as [$etat, $distribution]) {
        if (is_file($racine . $etat) && is_file($racine . $distribution)) {
            return [$racine . $etat, $racine . $distribution];
        }
    }

    return null;
}

/** Le site est-il en maintenance (restauration en cours) ? */
function en_maintenance(?string $base = null): bool
{
    $temoin = dirname($base ?? chemin_base()) . '/' . TEMOIN_MAINTENANCE;
    if (!is_file($temoin)) {
        return false;
    }
    return !temoin_automatique_perime($temoin);
}

/** Témoin automatique resté après un arrêt brutal de PHP : il ne bloque plus rien au bout de 10 minutes. */
function temoin_automatique_perime(string $temoin): bool
{
    $premiere = strtok((string) @file_get_contents($temoin, false, null, 0, 128), "\n");

    return trim((string) $premiere) === MARQUE_TEMOIN_AUTOMATIQUE && (int) @filemtime($temoin) < time() - 600;
}

/** Dossier des sauvegardes d'une base : stockage/sauvegardes/ à côté de la base. */
function dossier_sauvegardes(string $base): string
{
    return dirname($base) . '/sauvegardes';
}

/** Préfixe des sauvegardes d'une base : « base » pour base.sqlite, « test » pour la base des tests. */
function prefixe_sauvegardes(string $base): string
{
    return basename($base, '.sqlite');
}

/** Nom d'une sauvegarde faite par le site (rien d'autre n'est proposé à la restauration). */
function nom_sauvegarde_valide(string $nom, string $prefixe): bool
{
    return preg_match('/^' . preg_quote($prefixe, '/') . '-\d{4}-\d{2}-\d{2}-\d{6}(-\d{1,3})?\.sqlite$/D', $nom) === 1;
}

/**
 * Sauvegarde cohérente de la base, sans arrêter le site : `VACUUM INTO` (SQLite ≥ 3.27), sinon point de contrôle
 * du journal WAL puis copie du fichier. Nom : <base>-AAAA-MM-JJ-HHMMSS.sqlite (heure de Paris).
 * @return string chemin du fichier écrit
 */
function sauvegarder_base(string $base, ?string $dossier = null, ?int $maintenant = null): string
{
    if (!is_file($base)) {
        throw new \RuntimeException('Base introuvable : rien à sauvegarder.');
    }
    $cible = nouveau_chemin_de_sauvegarde($base, $dossier, $maintenant);
    try {
        $pdo = connexion($base);
        $version = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
        if (version_compare($version, '3.27.0', '>=')) {
            $requete = $pdo->prepare('VACUUM INTO ?');
            $requete->execute([$cible]);
        } else {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            if (!@copy($base, $cible)) {
                throw new \RuntimeException('Copie de la base impossible.');
            }
        }
        $pdo = null;
        @chmod($cible, 0640);
        verifier_sauvegarde($cible);
    } catch (\Throwable $erreur) {
        // Une sauvegarde ratée (disque plein, copie abîmée) ne reste jamais dans la liste.
        @unlink($cible);
        throw $erreur;
    }

    return $cible;
}

function nouveau_chemin_de_sauvegarde(string $base, ?string $dossier = null, ?int $maintenant = null): string
{
    $dossier ??= dossier_sauvegardes($base);
    if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
        throw new \RuntimeException('Impossible de créer le dossier des sauvegardes.');
    }
    $date = (new \DateTimeImmutable('@' . ($maintenant ?? time())))->setTimezone(new \DateTimeZone('Europe/Paris'));
    $racine = $dossier . '/' . prefixe_sauvegardes($base) . '-' . $date->format('Y-m-d-His');
    // Le nom est réservé (fichier vide créé de façon exclusive ; VACUUM INTO accepte un fichier vide) : deux
    // sauvegardes de la même seconde ont chacune le leur, et un échec n'efface que le sien.
    for ($n = 1; $n < 1000; ++$n) {
        $cible = $racine . ($n === 1 ? '' : '-' . $n) . '.sqlite';
        $reserve = @fopen($cible, 'x');
        if ($reserve !== false) {
            fclose($reserve);

            return $cible;
        }
    }
    throw new \RuntimeException('Impossible de nommer la sauvegarde.');
}

/**
 * Contrôle d'un fichier de sauvegarde : base SQLite intègre, avec la table des migrations du site et ses cousins.
 * @return array{migrations: list<string>, cousins: int}
 */
function verifier_sauvegarde(string $fichier): array
{
    if (!is_file($fichier) || filesize($fichier) < 512) {
        throw new \RuntimeException('Sauvegarde introuvable ou vide.');
    }
    $entete = (string) file_get_contents($fichier, false, null, 0, 16);
    if ($entete !== "SQLite format 3\0") {
        throw new \RuntimeException('Ce fichier n’est pas une base SQLite.');
    }
    $pdo = new PDO('sqlite:' . $fichier, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $integrite = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrite !== 'ok') {
        throw new \RuntimeException('Sauvegarde abîmée (contrôle d’intégrité : ' . mb_substr($integrite, 0, 80) . ').');
    }
    if (!base_installee($pdo)) {
        throw new \RuntimeException('Ce fichier n’est pas une base des Chorés du mariage (table des migrations absente).');
    }
    $migrations = $pdo->query('SELECT version FROM migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    $cousins = (int) $pdo->query('SELECT COUNT(*) FROM cousins')->fetchColumn();

    return ['migrations' => array_map('strval', $migrations), 'cousins' => $cousins];
}

/**
 * Réglages du lien en service (empreinte, version, date). null si la base est vide, abîmée ou sans lien (on restaure
 * alors avec le lien de la sauvegarde) ; exception si la base est saine mais que son lien ne se lit pas (occupée) :
 * mieux vaut refuser que remettre en service, sans le dire, un lien peut-être révoqué.
 * @return array<string, string>|null
 */
function lien_en_service(string $base): ?array
{
    try {
        $pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $installee = base_installee($pdo);
    } catch (\PDOException $erreur) {
        // 5 et 6 : SQLITE_BUSY et SQLITE_LOCKED (base occupée, pas abîmée) : refuser plutôt que remettre un vieux lien.
        if (in_array((int) ($erreur->errorInfo[1] ?? 0), [5, 6], true)) {
            throw new \RuntimeException('La base actuelle est occupée : réessaie dans une minute (rien n’a été remplacé).', 0, $erreur);
        }

        return null;
    } catch (\Throwable) {
        return null;
    }
    if (!$installee) {
        return null;
    }
    try {
        $lignes = $pdo->query("SELECT cle, valeur FROM reglages WHERE cle IN ('jeton_hash', 'jeton_version', 'jeton_cree_le')")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (\Throwable $erreur) {
        throw new \RuntimeException('Le lien en service ne se lit pas dans la base actuelle : rien n’a été remplacé.', 0, $erreur);
    }

    return isset($lignes['jeton_hash'], $lignes['jeton_version']) ? array_map('strval', $lignes) : null;
}

/** Une restauration a remplacé la base, mais une mise à jour de la base a échoué ensuite (le lien est déjà gardé). */
final class RestaurationPartielle extends \RuntimeException
{
}

/**
 * Remplace la base par une sauvegarde contrôlée, sous le témoin de maintenance (les visites reçoivent une page
 * « maintenance » au lieu d'ouvrir la base ; une seule restauration à la fois). Dans l'ordre :
 * 1. la sauvegarde est copiée près de la base sous un nom temporaire, et le lien en service y est écrit (les cousins
 *    et Patrice restent connectés, et un ancien lien ne revient pas) ;
 * 2. la base actuelle est copiée dans sauvegardes/avant-restauration/ (contrôlée si elle est lisible, brute sinon :
 *    une base vide ou abîmée n'empêche jamais de restaurer) ;
 * 3. la base actuelle et ses fichiers -wal / -shm sont mis de côté, la copie prend leur place (rename), puis ce qui a
 *    été mis de côté est effacé ; si la mise en place échoue, la base actuelle revient ;
 * 4. les migrations manquantes sont appliquées (échec : RestaurationPartielle, la sauvegarde reste en place).
 * @return array{remplacee: ?string, migrations: list<string>, cousins: int, lienGarde: bool}
 */
function restaurer_base(string $sauvegarde, string $base): array
{
    $contenu = verifier_sauvegarde($sauvegarde);
    $dossier = dirname($base);
    if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
        throw new \RuntimeException('Impossible de créer le dossier de stockage.');
    }
    $temoin = $dossier . '/' . TEMOIN_MAINTENANCE;
    if (is_file($temoin) && temoin_automatique_perime($temoin)) {
        @unlink($temoin);
    }
    $verrou = @fopen($temoin, 'x');
    if ($verrou === false) {
        throw new \RuntimeException('Une restauration est déjà en cours (ou le site est mis en maintenance à la main) : réessaie dans une minute.');
    }
    // Le témoin porte un jeton : seule cette restauration le retire (jamais celui d'une autre).
    $jeton = bin2hex(random_bytes(8));
    fwrite($verrou, MARQUE_TEMOIN_AUTOMATIQUE . "\n" . $jeton . "\n");
    fclose($verrou);
    $retirerTemoin = static function () use ($temoin, $jeton): void {
        if (str_contains((string) @file_get_contents($temoin, false, null, 0, 128), $jeton)) {
            @unlink($temoin);
        }
    };
    // Même si la page est fermée ou que PHP s'arrête, le témoin ne reste pas posé.
    ignore_user_abort(true);
    @set_time_limit(300);
    register_shutdown_function($retirerTemoin);
    $suffixe = '.' . bin2hex(random_bytes(4));
    foreach ([...(glob($base . '.restauration.*') ?: []), ...(glob($base . '.ancienne.*') ?: [])] as $reste) {
        @unlink($reste);
    }
    try {
        // Les requêtes déjà commencées finissent avant qu'on touche aux fichiers.
        usleep(500_000);
        $lien = is_file($base) ? lien_en_service($base) : null;

        $temporaire = $base . '.restauration' . $suffixe;
        if (!@copy($sauvegarde, $temporaire)) {
            throw new \RuntimeException('Copie de la sauvegarde impossible.');
        }
        @chmod($temporaire, 0640);
        if ($lien !== null) {
            $copie = new PDO('sqlite:' . $temporaire, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach ($lien as $cle => $valeur) {
                poser_reglage($copie, $cle, $valeur);
            }
            $copie->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $copie = null;
        }

        $avant = is_file($base) ? copier_avant_restauration($base, $sauvegarde) : null;

        // Avertissements muets (@) : en web, un avertissement deviendrait une exception avant la remise en place.
        $deCote = [];
        $remettre = static function () use (&$deCote): void {
            foreach ($deCote as $origine => $cote) {
                @rename($cote, $origine);
            }
        };
        foreach (['', '-wal', '-shm'] as $fin) {
            if (!is_file($base . $fin)) {
                continue;
            }
            if (!@rename($base . $fin, $base . '.ancienne' . $suffixe . $fin)) {
                $remettre();
                throw new \RuntimeException('La base actuelle n’a pas pu être mise de côté : rien n’a été remplacé.');
            }
            $deCote[$base . $fin] = $base . '.ancienne' . $suffixe . $fin;
        }
        if (!@rename($temporaire, $base)) {
            $remettre();
            throw new \RuntimeException('Mise en place de la sauvegarde impossible : la base actuelle est remise.');
        }
        foreach ($deCote as $cote) {
            @unlink($cote);
        }
        foreach (['-wal', '-shm'] as $fin) {
            @unlink($temporaire . $fin);
        }

        try {
            $pdo = connexion($base);
            $appliquees = migrer($pdo);
            // Tout dans le fichier principal : une connexion restée ouverte sur l'ancienne base, en se fermant, peut
            // effacer le fichier -wal du même nom (le site ferme sa connexion avant d'envoyer une vidéo).
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo = null;
        } catch (\Throwable $erreur) {
            throw new RestaurationPartielle('Sauvegarde remise en place, mais une mise à jour de la base a échoué : ' . $erreur->getMessage(), 0, $erreur);
        }
    } finally {
        if (isset($temporaire) && is_file($temporaire)) {
            @unlink($temporaire);
        }
        $retirerTemoin();
    }

    return ['remplacee' => $avant, 'migrations' => $appliquees, 'cousins' => $contenu['cousins'], 'lienGarde' => $lien !== null];
}

/**
 * Copie de la base actuelle avant une restauration : contrôlée si possible, brute sinon (base vide ou abîmée). Les 5
 * copies les plus récentes sont gardées ; la sauvegarde en cours de restauration n'est jamais élaguée.
 */
function copier_avant_restauration(string $base, ?string $aGarder = null): string
{
    $dossier = dossier_sauvegardes($base) . '/avant-restauration';
    try {
        $copie = sauvegarder_base($base, $dossier);
    } catch (\Throwable) {
        $copie = nouveau_chemin_de_sauvegarde($base, $dossier);
        foreach (['', '-wal', '-shm'] as $suffixe) {
            if (is_file($base . $suffixe) && !copy($base . $suffixe, $copie . $suffixe)) {
                throw new \RuntimeException('Copie de la base actuelle impossible : rien n’a été remplacé.');
            }
        }
    }
    elaguer_sauvegardes($dossier, prefixe_sauvegardes($base), 5, $aGarder);

    return $copie;
}

/** @return list<array{nom: string, taille: int, quand: int}> sauvegardes d'une base, la plus récente d'abord */
function liste_sauvegardes(string $dossier, string $prefixe = 'base'): array
{
    $liste = [];
    foreach (glob($dossier . '/*.sqlite') ?: [] as $fichier) {
        if (nom_sauvegarde_valide(basename($fichier), $prefixe)) {
            $liste[] = ['nom' => basename($fichier), 'taille' => (int) filesize($fichier), 'quand' => (int) filemtime($fichier)];
        }
    }
    // La date est dans le nom : une sauvegarde renvoyée depuis le PC (date de fichier récente) garde sa place.
    usort($liste, static fn (array $a, array $b): int => strnatcmp(basename($b['nom'], '.sqlite'), basename($a['nom'], '.sqlite')));

    return $liste;
}

/**
 * Garde les $garder sauvegardes les plus récentes d'une base (jamais $aGarder, chemin d'une sauvegarde à épargner) ;
 * @return int nombre de fichiers effacés
 */
function elaguer_sauvegardes(string $dossier, string $prefixe, int $garder, ?string $aGarder = null): int
{
    $effacees = 0;
    $epargnee = $aGarder !== null ? realpath($aGarder) : false;
    foreach (array_slice(liste_sauvegardes($dossier, $prefixe), max(1, $garder)) as $ancienne) {
        if ($epargnee !== false && realpath($dossier . '/' . $ancienne['nom']) === $epargnee) {
            continue;
        }
        foreach (['', '-wal', '-shm'] as $suffixe) {
            if (is_file($dossier . '/' . $ancienne['nom'] . $suffixe)) {
                unlink($dossier . '/' . $ancienne['nom'] . $suffixe);
            }
        }
        ++$effacees;
    }

    return $effacees;
}

/** Date lisible d'une sauvegarde, tirée de son nom (« 13/10/2026 à 21:04 »), ou null. */
function date_sauvegarde(string $nom): ?string
{
    if (preg_match('/-(\d{4})-(\d{2})-(\d{2})-(\d{2})(\d{2})\d{2}(-\d+)?\.sqlite$/D', $nom, $m) !== 1) {
        return null;
    }

    return "{$m[3]}/{$m[2]}/{$m[1]} à {$m[4]}:{$m[5]}";
}
