<?php

declare(strict_types=1);

namespace Chores;

// Lecture des jetons de couleur de public/css/jetons.css et mesure des contrastes WCAG 2.2.
// Utilisé par scripts/contraste.php (contrôle de composer check) et par /gabarits (contrastes affichés) :
// les couleurs ne sont écrites qu'une fois, dans jetons.css.

const SEUIL_TEXTE = 4.5;
const SEUIL_CONTOUR = 3.0;

/**
 * Paires de couleurs autorisées : [premier plan, arrière-plan, seuil, usage, thèmes concernés].
 * Toute nouvelle paire utilisée par une feuille s'ajoute ici.
 * @return list<array{0: string, 1: string, 2: float, 3: string, 4: list<string>}>
 */
function paires_de_couleurs(): array
{
    $tous = ['clair', 'sombre'];

    return [
        ['texte', 'fond', SEUIL_TEXTE, 'corps de page', $tous],
        ['texte', 'surface', SEUIL_TEXTE, 'cartes, prénoms, lignes de section', $tous],
        ['texte', 'surface-2', SEUIL_TEXTE, 'boutons secondaires, état « presque »', $tous],
        ['fond', 'texte', SEUIL_TEXTE, 'bannière hors ligne (inversée)', $tous],
        ['secondaire', 'fond', SEUIL_TEXTE, 'textes d’aide, pied de page', $tous],
        ['secondaire', 'surface', SEUIL_TEXTE, 'textes d’aide sur carte', $tous],
        ['secondaire', 'surface-2', SEUIL_TEXTE, 'textes d’aide sur surface secondaire', $tous],
        ['accent', 'fond', SEUIL_TEXTE, 'boutons contour (Retour, vitesses, Miroir, Boucle)', $tous],
        ['accent', 'surface', SEUIL_TEXTE, 'boutons contour sur carte', $tous],
        ['lien', 'fond', SEUIL_TEXTE, 'liens, « YouTube ↗ », horodatages', $tous],
        ['lien', 'surface', SEUIL_TEXTE, 'liens et horodatages sur carte', $tous],
        ['sur-grenat', 'grenat', SEUIL_TEXTE, 'bandeau, carte répétition, boutons pleins, vignettes', $tous],
        ['fond', 'accent', SEUIL_TEXTE, 'commande ou réponse choisie (plein accent)', $tous],
        ['grenat-inverse', 'sur-grenat', SEUIL_TEXTE, 'texte sur la pastille claire de la carte grenat (sondage, réponse choisie)', $tous],
        ['sur-or', 'or', SEUIL_TEXTE, 'Lecture, « Je maîtrise », badge Miroir, survol et focus', $tous],
        ['vert', 'fond', SEUIL_TEXTE, '« Présents : », confirmations', $tous],
        ['vert', 'surface', SEUIL_TEXTE, '« décidée » sur carte', $tous],
        ['orange-texte', 'fond', SEUIL_TEXTE, 'encart « Qui s’en occupe ? »', $tous],
        ['orange-texte', 'surface', SEUIL_TEXTE, '« Choré à créer » sur carte', $tous],
        ['orange-texte', 'surface-2', SEUIL_TEXTE, '« Choré à créer » sur surface secondaire', $tous],
        ['sur-orange', 'orange', SEUIL_TEXTE, 'bouton « … s’en occupe » choisi', $tous],
        ['sur-voile', 'voile', SEUIL_TEXTE, 'voiles et surimpressions sur la vidéo (pire cas : image blanche)', $tous],
        ['decompte', 'voile', SEUIL_TEXTE, 'chiffres du décompte sur la vidéo', $tous],
        ['decompte', 'fond', SEUIL_TEXTE, 'chiffres du décompte hors vidéo', ['sombre']],
        ['accent', 'fond', SEUIL_CONTOUR, 'bord des boutons contour', $tous],
        ['accent', 'surface', SEUIL_CONTOUR, 'bord des boutons contour sur carte (niveau choisi)', $tous],
        ['orange', 'fond', SEUIL_CONTOUR, 'bord de l’encart orange', $tous],
        ['orange', 'surface', SEUIL_CONTOUR, 'repère des avertissements', $tous],
        ['secondaire', 'fond', SEUIL_CONTOUR, 'bord des champs', $tous],
        ['secondaire', 'surface', SEUIL_CONTOUR, 'bord des champs dans un bloc', $tous],
        ['vert', 'surface', SEUIL_CONTOUR, 'repère des confirmations', $tous],
        ['focus-filet', 'or', SEUIL_CONTOUR, 'filet du focus sur un bouton devenu or', ['clair']],
        ['focus-filet', 'fond', SEUIL_CONTOUR, 'filet sombre du focus sur le fond', $tous],
        ['focus-filet', 'surface', SEUIL_CONTOUR, 'contour du focus des champs dans un bloc', $tous],
        ['focus', 'grenat', SEUIL_CONTOUR, 'contour de focus sur le bandeau et la carte répétition', $tous],
        ['focus', 'fond', SEUIL_CONTOUR, 'contour de focus or sur le fond sombre', ['sombre']],
        ['focus', 'surface', SEUIL_CONTOUR, 'contour de focus or sur carte sombre', ['sombre']],
    ];
}

/**
 * Lit les trois blocs de jetons de jetons.css : clair, sombre ([data-theme="sombre"]) et sombre-systeme
 * (requête prefers-color-scheme). Valeurs en hexadécimal (#rgb, #rrggbb ou #rrggbbaa).
 * @return array{clair: array<string, string>, sombre: array<string, string>, sombre-systeme: array<string, string>}
 */
function lire_jetons(string $css): array
{
    $blocs = [
        'clair' => '/:root,\s*\[data-theme="clair"\]\s*\{([^}]*)\}/',
        'sombre' => '/^\[data-theme="sombre"\]\s*\{([^}]*)\}/m',
        'sombre-systeme' => '/:root:not\(\[data-theme="clair"\]\)\s*\{([^}]*)\}/',
    ];
    $jetons = [];
    foreach ($blocs as $nom => $motif) {
        $jetons[$nom] = [];
        if (preg_match($motif, $css, $bloc)) {
            preg_match_all('/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $bloc[1], $m, PREG_SET_ORDER);
            foreach ($m as [, $cle, $valeur]) {
                $jetons[$nom][$cle] = strtolower($valeur);
            }
        }
    }

    return $jetons;
}

/** @return array{0: int, 1: int, 2: int, 3: float} rouge, vert, bleu (0-255), opacité (0-1) */
function couleur_rvba(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
        throw new \InvalidArgumentException('Couleur illisible : #' . $hex);
    }
    $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

    return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)), $alpha];
}

/** Couleur translucide posée sur un fond opaque (blanc par défaut : pire cas sous un texte clair). */
function composer(string $hex, string $dessous = '#ffffff'): string
{
    [$r, $v, $b, $a] = couleur_rvba($hex);
    [$r2, $v2, $b2] = couleur_rvba($dessous);
    $melange = static fn (int $x, int $y): int => (int) round($a * $x + (1 - $a) * $y);

    return sprintf('#%02x%02x%02x', $melange($r, $r2), $melange($v, $v2), $melange($b, $b2));
}

function luminance(string $hex): float
{
    [$r, $v, $b] = couleur_rvba($hex);
    $canal = static function (int $c): float {
        $c /= 255;

        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $canal($r) + 0.7152 * $canal($v) + 0.0722 * $canal($b);
}

/** Rapport de contraste WCAG ; un fond translucide est d'abord composé sur blanc (pire cas pour un texte clair). */
function rapport_contraste(string $avant, string $arriere): float
{
    $arriere = couleur_rvba($arriere)[3] < 1 ? composer($arriere) : $arriere;
    $avant = couleur_rvba($avant)[3] < 1 ? composer($avant, $arriere) : $avant;
    [$clair, $sombre] = [max(luminance($avant), luminance($arriere)), min(luminance($avant), luminance($arriere))];

    return ($clair + 0.05) / ($sombre + 0.05);
}

/**
 * Mesure toutes les paires dans les deux thèmes.
 * @param array{clair: array<string, string>, sombre: array<string, string>, sombre-systeme: array<string, string>} $jetons
 * @return list<array{theme: string, avant: string, arriere: string, seuil: float, usage: string, rapport: ?float, ok: bool, erreur: ?string}>
 */
function mesurer_contrastes(array $jetons): array
{
    $resultats = [];
    foreach (paires_de_couleurs() as [$avant, $arriere, $seuil, $usage, $themes]) {
        foreach ($themes as $theme) {
            $valeurs = $jetons[$theme] ?? [];
            if (!isset($valeurs[$avant], $valeurs[$arriere])) {
                $resultats[] = ['theme' => $theme, 'avant' => $avant, 'arriere' => $arriere, 'seuil' => $seuil, 'usage' => $usage,
                    'rapport' => null, 'ok' => false, 'erreur' => "jeton --{$avant} ou --{$arriere} introuvable"];
                continue;
            }
            $r = rapport_contraste($valeurs[$avant], $valeurs[$arriere]);
            $resultats[] = ['theme' => $theme, 'avant' => $avant, 'arriere' => $arriere, 'seuil' => $seuil, 'usage' => $usage,
                'rapport' => $r, 'ok' => $r >= $seuil, 'erreur' => null];
        }
    }

    return $resultats;
}

/** « 4,5:1 » à la française. */
function format_rapport(float $rapport): string
{
    return number_format(floor($rapport * 100) / 100, 2, ',', '') . ':1';
}
