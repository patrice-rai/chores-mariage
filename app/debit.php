<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Limitation de débit sans CAPTCHA (spec § 11). La clé est un SHA-256 de l'action, de l'adresse IP et d'un sel
// qui change chaque jour : l'adresse n'est jamais rangée en clair et ne se recoupe pas d'un jour à l'autre.

const LIMITES_DEBIT = [
    'entree' => [['minute', 10], ['heure', 60]],
    'prenom' => [['heure', 20]],
];

function cle_debit(string $secret, string $action, string $ip, int $maintenant): string
{
    $sel = hash_hmac('sha256', 'sel-debit:' . gmdate('Y-m-d', $maintenant), $secret);

    return hash('sha256', $action . '|' . reseau_ip($ip) . '|' . $sel);
}

/** IPv6 : le préfixe /64 (une box change d'adresse dans son /64) ; IPv4 : l'adresse. */
function reseau_ip(string $ip): string
{
    $octets = @inet_pton($ip);
    if ($octets === false || strlen($octets) !== 16) {
        return $ip;
    }
    if (str_starts_with($octets, str_repeat("\0", 10) . "\xff\xff")) {
        return (string) inet_ntop(substr($octets, 12));
    }

    return bin2hex(substr($octets, 0, 8)) . '::/64';
}

function fenetre_debit(string $duree, int $maintenant): string
{
    return match ($duree) {
        'minute' => gmdate('Y-m-d\TH:i', $maintenant),
        'heure' => gmdate('Y-m-d\TH', $maintenant),
        'jour' => gmdate('Y-m-d', $maintenant),
        default => throw new \InvalidArgumentException('Fenêtre de débit inconnue.'),
    };
}

/**
 * Compte un essai et dit s'il reste sous les limites de l'action.
 * @param list<array{0: string, 1: int}>|null $limites
 */
function debit_autorise(PDO $pdo, string $secret, string $action, string $ip, ?array $limites = null, ?int $maintenant = null): bool
{
    $limites ??= LIMITES_DEBIT[$action] ?? throw new \InvalidArgumentException('Action de débit inconnue.');
    $maintenant ??= time();
    $cle = cle_debit($secret, $action, $ip, $maintenant);

    $autorise = true;
    $pdo->beginTransaction();
    try {
        $ajout = $pdo->prepare(
            'INSERT INTO debit (cle, fenetre, compteur) VALUES (?, ?, 1)
             ON CONFLICT(cle, fenetre) DO UPDATE SET compteur = compteur + 1'
        );
        $lecture = $pdo->prepare('SELECT compteur FROM debit WHERE cle = ? AND fenetre = ?');
        foreach ($limites as [$duree, $maximum]) {
            $fenetre = fenetre_debit($duree, $maintenant);
            $ajout->execute([$cle, $fenetre]);
            $lecture->execute([$cle, $fenetre]);
            if ((int) $lecture->fetchColumn() > $maximum) {
                $autorise = false;
            }
        }
        $pdo->commit();
    } catch (\Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }

    if (random_int(1, 50) === 1) {
        try {
            purger_debit($pdo, $maintenant);
        } catch (\Throwable $erreur) {
            error_log('[chores] purge du débit remise : ' . $erreur->getMessage());
        }
    }

    return $autorise;
}

/**
 * Réserve un essai sur plusieurs compteurs à la fois (prénom et adresse, heure et jour) : tous sont lus d'abord, et
 * aucun n'est compté si l'un d'eux est plein. Sert aux vidéos : un envoi refusé ne consomme rien (sinon, réessayer
 * pendant l'heure bloquée viderait aussi le quota du jour).
 * @param list<array{0: string, 1: string, 2: list<array{0: string, 1: int}>}> $compteurs [action, identité, limites]
 */
function debit_reserver(PDO $pdo, string $secret, array $compteurs, ?int $maintenant = null): bool
{
    $maintenant ??= time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $lecture = $pdo->prepare('SELECT compteur FROM debit WHERE cle = ? AND fenetre = ?');
        $places = [];
        foreach ($compteurs as [$action, $identite, $limites]) {
            $cle = cle_debit($secret, $action, $identite, $maintenant);
            foreach ($limites as [$duree, $maximum]) {
                $fenetre = fenetre_debit($duree, $maintenant);
                $lecture->execute([$cle, $fenetre]);
                if ((int) $lecture->fetchColumn() >= $maximum) {
                    $pdo->exec('ROLLBACK');

                    return false;
                }
                $places[] = [$cle, $fenetre];
            }
        }
        $ajout = $pdo->prepare(
            'INSERT INTO debit (cle, fenetre, compteur) VALUES (?, ?, 1) ON CONFLICT(cle, fenetre) DO UPDATE SET compteur = compteur + 1'
        );
        foreach ($places as $place) {
            $ajout->execute($place);
        }
        $pdo->exec('COMMIT');
    } catch (\Throwable $erreur) {
        $pdo->exec('ROLLBACK');
        throw $erreur;
    }

    return true;
}

/** Oublie les fenêtres de plus de deux jours. */
function purger_debit(PDO $pdo, ?int $maintenant = null): int
{
    $limite = gmdate('Y-m-d', ($maintenant ?? time()) - 2 * 86400);
    $requete = $pdo->prepare('DELETE FROM debit WHERE fenetre < ?');
    $requete->execute([$limite]);

    return $requete->rowCount();
}

/**
 * Journalise un refus de débit (une ligne par action, par prénom et par minute : le journal ne doit pas se remplir de
 * refus, mais deux cousins refusés la même minute sont tous les deux notés).
 */
function journaliser_refus_debit(Contexte $c, string $action): void
{
    $deja = $c->pdo()->prepare("SELECT 1 FROM journal WHERE type = 'refus_debit' AND detail = ? AND quand >= ? AND cousin_id IS ? LIMIT 1");
    $deja->execute([$action, maintenant_utc(time() - 60), $c->cousin['id'] ?? null]);
    if ($deja->fetchColumn() === false) {
        journaliser($c->pdo(), 'refus_debit', $c->cousin['id'] ?? null, $action);
    }
}

function reponse_trop_d_essais(Contexte $c, string $action): Reponse
{
    journaliser_refus_debit($c, $action);

    // Les écritures des cousins (niveaux, placements, vidéos, commentaires…) sont comptées à l'heure : on ne promet
    // pas « une minute ».
    $messages = [
        'ecriture' => 'Trop de changements d’un coup. Réessaie un peu plus tard.',
        'video' => 'Tu as déjà envoyé beaucoup de vidéos : réessaie dans une heure (ou demain).',
        'commentaire' => 'Beaucoup de commentaires d’un coup : réessaie dans une heure.',
    ];
    $horaire = isset($messages[$action]);

    return page_erreur($c->config, 429, 'Doucement !', $messages[$action] ?? 'Trop d’essais d’un coup. Réessaie dans une minute.')
        ->avecEntete('Retry-After', $horaire ? (string) (3600 - time() % 3600) : '60');
}
