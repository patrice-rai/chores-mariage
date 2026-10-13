<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Base SQLite (mode WAL) hors racine web, et migrations numérotées app/migrations/NNN-nom.sql.

function chemin_base(): string
{
    return RACINE . '/stockage/base.sqlite';
}

/** Témoin posé par les tests de bout en bout (scripts/e2e-outil.php) : le site sert alors la base de test. */
const TEMOIN_E2E = '/stockage/e2e.actif';

function chemin_base_de_test(): string
{
    return RACINE . '/stockage/test.sqlite';
}

/**
 * Base servie : la base de test seulement en développement et quand le témoin des tests existe ; sinon la vraie.
 * Les tests ne touchent donc jamais aux données de Patrice, et la production n'a aucun moyen d'en changer.
 */
function chemin_base_active(array $config): string
{
    return $config['ENV'] === 'dev' && is_file(RACINE . TEMOIN_E2E) ? chemin_base_de_test() : chemin_base();
}

/**
 * Scripts lancés en root (docker compose exec) : prendre l'identité du propriétaire du dossier de stockage, pour que
 * la base et ses fichiers -wal / -shm restent à Apache. Sans effet chez l'hébergeur (jamais root).
 */
function prendre_identite_du_stockage(): void
{
    if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
        return;
    }
    $dossier = dirname(chemin_base());
    $uid = is_dir($dossier) ? fileowner($dossier) : false;
    $gid = is_dir($dossier) ? filegroup($dossier) : false;
    if ($uid === false || $gid === false || $uid === 0) {
        return;
    }
    $compte = posix_getpwuid($uid);
    if ($compte !== false) {
        posix_initgroups($compte['name'], $gid);
    }
    if (!posix_setgid($gid) || !posix_setuid($uid)) {
        throw new \RuntimeException('Impossible de prendre l’identité du propriétaire du stockage.');
    }
}

function connexion(?string $chemin = null): PDO
{
    $chemin ??= chemin_base();
    if ($chemin !== ':memory:') {
        $dossier = dirname($chemin);
        if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage.');
        }
    }
    $pdo = new PDO('sqlite:' . $chemin, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($chemin !== ':memory:') {
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
    }

    return $pdo;
}

/**
 * Applique les migrations manquantes, dans l'ordre, chacune dans une transaction.
 * @return list<string> versions appliquées par cet appel
 */
function migrer(PDO $pdo, ?string $dossier = null): array
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY, applique_le TEXT NOT NULL)');
    $deja = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

    $appliquees = [];
    foreach (fichiers_migrations($dossier) as $version => $fichier) {
        if (in_array($version, $deja, true)) {
            continue;
        }
        $sql = (string) file_get_contents($fichier);
        // Verrou d'écriture pris d'emblée, puis nouvelle lecture : deux requêtes simultanées (installation, mise à
        // jour depuis l'administration, script) n'appliquent jamais deux fois la même migration.
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $existe = $pdo->prepare('SELECT 1 FROM migrations WHERE version = ?');
            $existe->execute([$version]);
            if ($existe->fetchColumn() !== false) {
                $pdo->exec('COMMIT');
                continue;
            }
            $pdo->exec($sql);
            $ajout = $pdo->prepare('INSERT INTO migrations (version, applique_le) VALUES (?, ?)');
            $ajout->execute([$version, maintenant_utc()]);
            $pdo->exec('COMMIT');
        } catch (\Throwable $erreur) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable) {
                // Le verrou n'avait pas été pris : rien à annuler.
            }
            throw new \RuntimeException("Migration {$version} refusée : " . $erreur->getMessage(), 0, $erreur);
        }
        $appliquees[] = $version;
    }

    return $appliquees;
}

/** @return array<string, string> version (« 001-socle ») → fichier, dans l'ordre */
function fichiers_migrations(?string $dossier = null): array
{
    $fichiers = glob(($dossier ?? __DIR__ . '/migrations') . '/[0-9][0-9][0-9]-*.sql') ?: [];
    sort($fichiers, SORT_STRING);
    $liste = [];
    foreach ($fichiers as $fichier) {
        $liste[basename($fichier, '.sql')] = $fichier;
    }

    return $liste;
}

/** Date UTC ISO 8601, telle qu'elle est rangée en base. */
function maintenant_utc(?int $horodatage = null): string
{
    return gmdate('Y-m-d\TH:i:s\Z', $horodatage ?? time());
}

/** Date UTC de la base → texte lisible en heure de Paris, par exemple « 13/10/2026 à 21:04 ». */
function date_paris(string $utc): string
{
    try {
        $date = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    } catch (\Exception) {
        return $utc;
    }

    return $date->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i');
}

/** Exécute $travail dans une transaction : tout ou rien. */
function en_transaction(PDO $pdo, callable $travail): mixed
{
    $pdo->beginTransaction();
    try {
        $resultat = $travail();
        $pdo->commit();

        return $resultat;
    } catch (\Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }
}

function reglage(PDO $pdo, string $cle): ?string
{
    $requete = $pdo->prepare('SELECT valeur FROM reglages WHERE cle = ?');
    $requete->execute([$cle]);
    $valeur = $requete->fetchColumn();

    return $valeur === false || $valeur === null ? null : (string) $valeur;
}

function poser_reglage(PDO $pdo, string $cle, ?string $valeur): void
{
    $requete = $pdo->prepare('INSERT INTO reglages (cle, valeur) VALUES (?, ?) ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur');
    $requete->execute([$cle, $valeur]);
}
