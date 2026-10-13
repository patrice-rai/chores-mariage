<?php

declare(strict_types=1);

namespace Chores;

// Formats d'affichage (mêmes règles que public/js/lecteur-calc.js) : temps « 0:17 », durée « 4 min 04 »,
// vitesse « 0,75 × », comptes « 4 × 8 », positions de scène en mots.

/** 17.4 → « 0:17 » ; 3725 → « 1:02:05 ». Les secondes sont tronquées, comme l'horloge du lecteur. */
function format_temps(float|int|null $secondes): string
{
    $s = max(0, (int) floor((float) $secondes));
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $r = $s % 60;

    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $r) : sprintf('%d:%02d', $m, $r);
}

/** 244 → « 4 min 04 » ; null → « durée inconnue ». */
function format_duree(?int $secondes): string
{
    if ($secondes === null || $secondes < 0) {
        return 'durée inconnue';
    }

    return sprintf('%d min %02d', intdiv($secondes, 60), $secondes % 60);
}

/** Durée d'une section, arrondie à la seconde : 16.9 → « 0:17 ». */
function format_duree_section(float $debut, float $fin): string
{
    return format_temps(round(max(0.0, $fin - $debut)));
}

function format_vitesse(float $vitesse): string
{
    return str_replace('.', ',', (string) $vitesse) . ' ×';
}

function format_comptes(int $comptes): string
{
    return $comptes . ' × 8';
}

/** Position (0-100, public en bas) en mots : « côté jardin (à gauche), au fond ». */
function position_en_mots(int $x, int $y): string
{
    $cote = $x < 40 ? 'côté jardin (à gauche)' : ($x > 60 ? 'côté cour (à droite)' : 'au centre');
    $profondeur = $y < 40 ? 'au fond' : ($y > 60 ? 'devant' : 'au milieu');

    return $cote . ', ' . $profondeur;
}

/** Prénom abrégé à trois lettres pour les points de scène (le prénom entier est dans la liste textuelle). */
function prenom_abrege(string $prenom): string
{
    return mb_substr($prenom, 0, 3);
}

/** 0 à 100 → pas de 5 (classes col-N, lig-N, p-N sans attribut style). */
function pas_de_cinq(float|int $valeur): int
{
    return (int) round(max(0, min(100, (float) $valeur)) / 5);
}

/** Lien sortant acceptable : https:// seulement (jamais javascript:, data:, http:). */
function lien_sortant_valide(?string $url): bool
{
    return $url !== null && preg_match('#^https://[A-Za-z0-9.-]+(?::\d{1,5})?(?:/[^\s"<>]*)?$#D', $url) === 1;
}

/** « de » élidé devant une voyelle : « d’Alex », « de Fabien » (« un cousin » : « d’un cousin »). */
function de(string $nom): string
{
    return (preg_match('/^[aeiouyhâàéèêëîïôöûüAEIOUYHÂÀÉÈÊËÎÏÔÖÛÜ]/u', $nom) === 1 ? 'd’' : 'de ') . $nom;
}
