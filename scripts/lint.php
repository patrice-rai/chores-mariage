<?php

declare(strict_types=1);

// php -l sur tous les fichiers PHP du projet (hors vendor/, node_modules/, stockage/).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$racine = dirname(__DIR__);
$dossiers = ['app', 'public', 'scripts', 'tests'];
$fichiers = [$racine . '/config.example.php'];
foreach ($dossiers as $dossier) {
    if (!is_dir($racine . '/' . $dossier)) {
        continue;
    }
    $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/' . $dossier, FilesystemIterator::SKIP_DOTS));
    foreach ($iterateur as $fichier) {
        if ($fichier->isFile() && $fichier->getExtension() === 'php') {
            $fichiers[] = $fichier->getPathname();
        }
    }
}

$erreurs = 0;
foreach ($fichiers as $fichier) {
    $sortie = [];
    exec('php -l ' . escapeshellarg($fichier) . ' 2>&1', $sortie, $code);
    if ($code !== 0) {
        $erreurs++;
        fwrite(STDERR, implode("\n", $sortie) . "\n");
    }
}

fwrite(STDOUT, sprintf("Lint : %d fichier(s) PHP, %d erreur(s).\n", count($fichiers), $erreurs));
exit($erreurs === 0 ? 0 : 1);
