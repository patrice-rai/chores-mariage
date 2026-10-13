<?php

declare(strict_types=1);

namespace Chores;

// Données de démonstration de /gabarits et du mode répétition « Démo » (phase 3). Rien ici n'est un fait : la
// chanson s'appelle « Démo », ses sections « Section A/B/C », ses positions de scène sont géométriques ; aucune
// n'est importée en base. Les prénoms des cousins (des faits) servent seulement à nommer les points.
// La vidéo est fabriquée par scripts/video-demo.mjs (canvas + MediaRecorder, 20 s), jamais versionnée.

const DEMO_VIDEO = '/stockage/medias/demo.webm';

/** @return array{cle: string, titre: string, film: string, duree_s: int, bpm: ?int, bpm_verifie: int} */
function demo_chanson(): array
{
    // Tempo inconnu : le décompte part à 120 et la page dit « tempo à régler » (même règle que les vraies chansons).
    return ['cle' => 'demo', 'titre' => 'Démo', 'film' => 'Film de démonstration', 'duree_s' => 20, 'bpm' => null, 'bpm_verifie' => 0];
}

/** @return list<array{nom: string, debut: float, fin: float, comptes: int}> */
function demo_sections(): array
{
    return [
        ['nom' => 'Section A', 'debut' => 0.0, 'fin' => 8.0, 'comptes' => 2],
        ['nom' => 'Section B', 'debut' => 8.0, 'fin' => 16.0, 'comptes' => 2],
        ['nom' => 'Section C', 'debut' => 16.0, 'fin' => 20.0, 'comptes' => 1],
    ];
}

function demo_video_chemin(): string
{
    return RACINE . DEMO_VIDEO;
}

/**
 * Huit points (les neuf cousins moins Nadine) : deux rangées pour A et C, une ligne pour B.
 * @return list<list<array{prenom: string, x: int, y: int}>> une liste de points par section
 */
function demo_placements(): array
{
    $prenoms = ['Alex', 'Catherine', 'Fabien', 'Flobert', 'Jessica', 'Josiane', 'Patrice', 'Stéphane'];
    $deuxRangees = [[15, 35], [35, 35], [65, 35], [85, 35], [15, 65], [35, 65], [65, 65], [85, 65]];
    $ligne = [[15, 50], [25, 50], [35, 50], [45, 50], [55, 50], [65, 50], [75, 50], [85, 50]];
    $points = static fn (array $positions): array => array_map(
        static fn (string $prenom, array $p): array => ['prenom' => $prenom, 'x' => $p[0], 'y' => $p[1]],
        $prenoms,
        $positions
    );

    return [$points($deuxRangees), $points($ligne), $points($deuxRangees)];
}
