<?php

declare(strict_types=1);

// Icônes de la PWA (spec § 8) : aplat grenat et le chiffre « 8 » en Yeseva One, dessinés avec GD et FreeType à partir
// de la police locale (public/polices/, woff2 lue par FreeType) ; couleurs lues dans public/css/jetons.css. Aucune
// image générée par IA, aucun fichier téléchargé. Le chiffre tient dans la zone sûre des icônes « maskable » (80 %).
// Usage (dans le conteneur, GD avec FreeType) : php scripts/icones.php → public/icones/icone-192.png,
// icone-512.png, apple-touch-icon.png (180). Relancer après un changement de police ou de couleur.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

if (!function_exists('imagettftext')) {
    fwrite(STDERR, "GD avec FreeType est nécessaire (le conteneur Docker l'a).\n");
    exit(1);
}

$jetons = Chores\lire_jetons((string) file_get_contents(Chores\RACINE . '/public/css/jetons.css'));
$grenat = $jetons['clair']['grenat'] ?? null;
$surGrenat = $jetons['clair']['sur-grenat'] ?? null;
if (!is_string($grenat) || !is_string($surGrenat)) {
    fwrite(STDERR, "Jetons --grenat ou --sur-grenat introuvables.\n");
    exit(1);
}
$police = Chores\RACINE . '/public/polices/yeseva-one-latin-400-normal-v5-3-0.woff2';
$dossier = Chores\RACINE . '/public/icones';
if (!is_dir($dossier)) {
    mkdir($dossier, 0755, true);
}

foreach (['icone-192.png' => 192, 'icone-512.png' => 512, 'apple-touch-icon.png' => 180] as $nom => $taille) {
    $image = imagecreatetruecolor($taille, $taille);
    $fond = imagecolorallocate($image, ...array_slice(Chores\couleur_rvba($grenat), 0, 3));
    $encre = imagecolorallocate($image, ...array_slice(Chores\couleur_rvba($surGrenat), 0, 3));
    imagefilledrectangle($image, 0, 0, $taille - 1, $taille - 1, $fond);
    imagealphablending($image, true);
    // Le chiffre occupe environ 55 % de la hauteur : il reste dans la zone sûre (cercle de 80 %).
    $corps = $taille * 0.5;
    $boite = imagettfbbox($corps, 0, $police, '8');
    $largeur = $boite[2] - $boite[0];
    $hauteur = $boite[1] - $boite[7];
    $x = (int) round(($taille - $largeur) / 2 - $boite[0]);
    $y = (int) round(($taille + $hauteur) / 2 - $boite[1]);
    imagettftext($image, $corps, 0, $x, $y, $encre, $police, '8');
    imagepng($image, $dossier . '/' . $nom, 9);
    imagedestroy($image);
    fwrite(STDOUT, sprintf("%s : %d × %d px, %d octets\n", $nom, $taille, $taille, filesize($dossier . '/' . $nom)));
}
