<?php

declare(strict_types=1);

// Garde de la CSP sans 'unsafe-inline' : aucun attribut style=, aucun <script> inline, aucun gestionnaire on*=
// dans les vues (app/vues/) et les pages statiques (public/*.html) ; aucune ressource externe (http:// ou https://
// dans un src, href de feuille de style ou url() CSS) ; aucun emoji dans les vues ; aucune couleur écrite hors de
// public/css/jetons.css (hexadécimal, rgb(), hsl()… : feuilles, scripts et vues n'utilisent que les variables).
// Usage : php scripts/lint-inline.php [racine du projet] (la racine en argument sert aux tests).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$racine = rtrim($argv[1] ?? dirname(__DIR__), '/\\');
$cibles = [];
foreach (['app/vues', 'public'] as $dossier) {
    if (!is_dir($racine . '/' . $dossier)) {
        continue;
    }
    $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/' . $dossier, FilesystemIterator::SKIP_DOTS));
    foreach ($iterateur as $fichier) {
        if ($fichier->isFile() && in_array($fichier->getExtension(), ['php', 'html', 'css', 'js', 'mjs'], true)) {
            $cibles[] = $fichier->getPathname();
        }
    }
}

$regles = [
    'attribut style' => ['/(<[a-z][^>]*\s)style\s*=/i', ['php', 'html']],
    'script inline' => ['/<script(?![^>]*\ssrc\s*=)[^>]*>/i', ['php', 'html']],
    'gestionnaire on*=' => ['/<[a-z][^>]*\son[a-z]+\s*=/i', ['php', 'html']],
    'javascript: dans un lien' => ['/href\s*=\s*["\']\s*javascript:/i', ['php', 'html']],
    'ressource externe' => ['/(<(script|img|iframe|video|audio|source|link)[^>]*\s(src|href)\s*=\s*["\']?\s*(https?:)?\/\/)|(url\(\s*["\']?\s*(https?:)?\/\/)|(@import\s+["\']?(url\()?\s*["\']?\s*(https?:)?\/\/)/i', ['php', 'html', 'css']],
    'emoji' => ['/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{FE0F}]/u', ['php', 'html', 'css', 'js', 'mjs']],
    'couleur hors jetons' => ['/((?<![&\w])(?<!href=")(?<!href=\')#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?(?:[0-9a-fA-F]{2})?\b)|(\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\s*\()/i', ['php', 'html', 'css', 'js', 'mjs']],
];

$defauts = 0;
foreach ($cibles as $fichier) {
    $texte = (string) file_get_contents($fichier);
    $extension = pathinfo($fichier, PATHINFO_EXTENSION);
    foreach ($regles as $nom => [$motif, $extensions]) {
        if (!in_array($extension, $extensions, true)) {
            continue;
        }
        if ($nom === 'couleur hors jetons' && basename($fichier) === 'jetons.css') {
            continue;
        }
        if (preg_match_all($motif, $texte, $trouves, PREG_OFFSET_CAPTURE)) {
            foreach ($trouves[0] as [$extrait, $position]) {
                $ligne = substr_count(substr($texte, 0, $position), "\n") + 1;
                fwrite(STDERR, sprintf("%s:%d : %s\n", substr($fichier, strlen($racine) + 1), $ligne, $nom));
                $defauts++;
            }
        }
    }
}

fwrite(STDOUT, sprintf("Lint inline : %d fichier(s), %d défaut(s).\n", count($cibles), $defauts));
exit($defauts === 0 ? 0 : 1);
