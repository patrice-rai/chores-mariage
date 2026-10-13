<?php

declare(strict_types=1);

// Contrastes WCAG 2.2 de toutes les paires de couleurs autorisées (app/jetons.php), dans les deux thèmes :
// texte ≥ 4,5:1, contours, focus et repères ≥ 3:1. Les couleurs sont lues dans public/css/jetons.css (ou dans le
// fichier passé en argument, pour les tests). Échoue aussi si un jeton manque, si le bloc sombre du système diffère
// du bloc [data-theme="sombre"], ou si une couleur est écrite hors de jetons.css (contrôlé par lint-inline.php).
// Usage : php scripts/contraste.php [chemin/vers/jetons.css]

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/jetons.php';

$fichier = $argv[1] ?? dirname(__DIR__) . '/public/css/jetons.css';
$css = @file_get_contents($fichier);
if ($css === false) {
    fwrite(STDERR, "Contrastes : feuille introuvable ({$fichier}).\n");
    exit(1);
}

$jetons = Chores\lire_jetons($css);
$echecs = 0;

foreach (['clair', 'sombre', 'sombre-systeme'] as $bloc) {
    if ($jetons[$bloc] === []) {
        fwrite(STDERR, "Contrastes : bloc de jetons « {$bloc} » introuvable.\n");
        $echecs++;
    }
}
if ($jetons['sombre'] !== $jetons['sombre-systeme']) {
    $differences = array_keys(array_diff_assoc($jetons['sombre'], $jetons['sombre-systeme']) + array_diff_assoc($jetons['sombre-systeme'], $jetons['sombre']));
    fwrite(STDERR, 'Contrastes : le bloc sombre du système diffère de [data-theme="sombre"] (--' . implode(', --', $differences) . ").\n");
    $echecs++;
}

$resultats = Chores\mesurer_contrastes($jetons);
foreach ($resultats as $r) {
    if ($r['erreur'] !== null) {
        fwrite(STDERR, sprintf("ÉCHEC %-7s %s (%s)\n", $r['theme'], $r['erreur'], $r['usage']));
        $echecs++;
        continue;
    }
    if (!$r['ok']) {
        $echecs++;
    }
    fwrite($r['ok'] ? STDOUT : STDERR, sprintf(
        "%s %-7s %-13s sur %-10s %5.2f:1 (seuil %.1f) %s\n",
        $r['ok'] ? 'ok   ' : 'ÉCHEC',
        $r['theme'],
        $r['avant'],
        $r['arriere'],
        $r['rapport'],
        $r['seuil'],
        $r['usage']
    ));
}

fwrite(STDOUT, sprintf("Contrastes : %d paire(s) mesurée(s), %d échec(s).\n", count($resultats), $echecs));
exit($echecs === 0 ? 0 : 1);
