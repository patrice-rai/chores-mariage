<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Journal minimal : type, prénom, détail court, date UTC. Jamais d'IP, jamais de user-agent, jamais de secret.

const TYPES_JOURNAL = [
    'entree' => 'Entrée par le lien',
    'prenom' => 'Choix du prénom',
    'niveau' => 'Niveau',
    'presence' => 'Présence',
    'video_ajout' => 'Vidéo ajoutée',
    'video_suppr' => 'Vidéo supprimée',
    'commentaire' => 'Commentaire',
    'placement' => 'Placement',
    'createur' => 'Qui s’en occupe',
    'check' => 'Check-list',
    'admin_lien' => 'Nouveau lien',
    'admin_modif' => 'Administration',
    'refus_debit' => 'Trop d’essais',
    'refus_jeton' => 'Lien refusé',
];

function journaliser(PDO $pdo, string $type, ?int $cousinId = null, string $detail = ''): void
{
    if (!array_key_exists($type, TYPES_JOURNAL)) {
        throw new \InvalidArgumentException('Type de journal inconnu.');
    }
    $detail = mb_substr(trim(preg_replace('/\s+/u', ' ', $detail) ?? ''), 0, 200);
    $requete = $pdo->prepare('INSERT INTO journal (quand, type, cousin_id, detail) VALUES (?, ?, ?, ?)');
    $requete->execute([maintenant_utc(), $type, $cousinId, $detail === '' ? null : $detail]);

    // Sans cron chez l'hébergeur, la purge à un an se fait de temps en temps ici.
    if (random_int(1, 200) === 1) {
        try {
            purger_journal($pdo);
        } catch (\Throwable $erreur) {
            error_log('[chores] purge du journal remise : ' . $erreur->getMessage());
        }
    }
}

/** Supprime les lignes de plus d'un an. @return int lignes supprimées */
function purger_journal(PDO $pdo, ?int $maintenant = null): int
{
    $limite = maintenant_utc(($maintenant ?? time()) - 365 * 86400);
    $requete = $pdo->prepare('DELETE FROM journal WHERE quand < ?');
    $requete->execute([$limite]);

    return $requete->rowCount();
}

/** @return list<array{quand: string, type: string, prenom: ?string, detail: ?string}> */
function dernieres_lignes_journal(PDO $pdo, int $nombre): array
{
    $requete = $pdo->prepare(
        'SELECT j.quand, j.type, c.prenom, j.detail FROM journal j LEFT JOIN cousins c ON c.id = j.cousin_id
         ORDER BY j.id DESC LIMIT ?'
    );
    $requete->bindValue(1, $nombre, PDO::PARAM_INT);
    $requete->execute();

    return $requete->fetchAll();
}
