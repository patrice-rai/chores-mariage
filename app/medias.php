<?php

declare(strict_types=1);

namespace Chores;

// Service des fichiers vidéo hors racine web (spec § 2 « /m/{id} », § 11) : type MIME fourni par l'appelant (jamais
// deviné à la volée), Accept-Ranges, réponses 206 pour une plage, 416 pour une plage impossible. Le fichier est
// envoyé par morceaux (envoyer_portion), jamais chargé en mémoire. Utilisé en phase 3 pour la vidéo de démonstration,
// en phase 5 pour /m/{id} après contrôle du cookie d'accès.

/**
 * Lit un en-tête Range d'une seule plage d'octets.
 * @return array{0: int, 1: int}|null|false [début, fin incluse] ; null : pas de plage exploitable (réponse
 *         complète) ; false : plage impossible à servir (416).
 */
function plage_http(?string $range, int $taille): array|null|false
{
    if ($range === null || $range === '') {
        return null;
    }
    // Plusieurs plages, autre unité ou syntaxe inconnue : on envoie le fichier entier (RFC 9110 § 14.2).
    if (!preg_match('/^bytes=(\d{0,18})-(\d{0,18})$/', trim($range), $m) || ($m[1] === '' && $m[2] === '')) {
        return null;
    }
    if ($taille <= 0) {
        return false;
    }
    if ($m[1] === '') {
        // « bytes=-500 » : les 500 derniers octets.
        $longueur = (int) $m[2];
        if ($longueur === 0) {
            return false;
        }

        return [max(0, $taille - $longueur), $taille - 1];
    }
    $debut = (int) $m[1];
    $fin = $m[2] === '' ? $taille - 1 : min((int) $m[2], $taille - 1);
    if ($debut >= $taille || $fin < $debut) {
        return false;
    }

    return [$debut, $fin];
}

/** Réponse qui sert un fichier (entier ou une plage), ou 404 s'il n'existe pas. */
function servir_fichier(Contexte $c, string $chemin, string $mime, string $cache = 'private, no-cache'): Reponse
{
    if (!is_file($chemin) || !is_readable($chemin)) {
        return page_introuvable($c);
    }
    $taille = (int) filesize($chemin);
    $plage = plage_http($c->entete('range'), $taille);
    $entetes = [
        'Content-Type' => $mime,
        'Accept-Ranges' => 'bytes',
        'Content-Disposition' => 'inline',
        'Cache-Control' => $cache,
        'Last-Modified' => gmdate('D, d M Y H:i:s', (int) filemtime($chemin)) . ' GMT',
    ];
    if ($plage === false) {
        return new Reponse(416, '', ['Content-Range' => 'bytes */' . $taille, 'Accept-Ranges' => 'bytes', 'Cache-Control' => $cache]);
    }
    [$debut, $fin] = $plage ?? [0, $taille - 1];
    $longueur = $taille === 0 ? 0 : $fin - $debut + 1;
    $reponse = new Reponse($plage === null ? 200 : 206, '', $entetes + ['Content-Length' => (string) $longueur]);
    if ($plage !== null) {
        $reponse->avecEntete('Content-Range', sprintf('bytes %d-%d/%d', $debut, $fin, $taille));
    }
    $reponse->fichier = ['chemin' => $chemin, 'debut' => $debut, 'longueur' => $longueur];

    return $reponse;
}
