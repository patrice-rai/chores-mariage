<?php

declare(strict_types=1);

// Génère la configuration locale de développement (à côté de app/) si elle n'existe pas encore :
// secrets aléatoires, BASE_PATH vide (site à la racine de http://127.0.0.1:8095), ENV = dev.
// N'affiche jamais les secrets. Ne touche jamais à une configuration existante.
//
// Essai du paquet (scripts/essai-paquet.mjs, dans le conteneur d'essai seulement) :
//   php config-dev.php --dossier=/home/…/chores-app --base=/chores --url=http://127.0.0.1:8096 --env=production
// écrit la configuration dans ce dossier, avec ces valeurs et des secrets aléatoires (toujours jamais affichés).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = ['dossier' => dirname(__DIR__), 'base' => '', 'url' => 'http://127.0.0.1:8095', 'env' => 'dev'];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(dossier|base|url|env)=(.*)$/sD', $argument, $m) !== 1) {
        fwrite(STDERR, "Option inconnue. Usage : php scripts/config-dev.php [--dossier=… --base=… --url=… --env=dev|production]\n");
        exit(1);
    }
    $options[$m[1]] = $m[2];
}
if (!in_array($options['env'], ['dev', 'production'], true)
    || preg_match('#^(/[A-Za-z0-9._~-]+)*$#D', $options['base']) !== 1
    || preg_match('#^https?://[A-Za-z0-9.-]+(:\d{1,5})?$#D', $options['url']) !== 1
    || !is_dir($options['dossier'])) {
    fwrite(STDERR, "Valeur refusée : --env=dev|production, --base=/dossier ou vide, --url=http(s)://hôte[:port], --dossier existant.\n");
    exit(1);
}

$cible = rtrim($options['dossier'], '/') . '/config.php';

/** Lisible par Apache (groupe www-data) et par personne d'autre ; sur un montage Windows, lisible par tous. */
$proteger = static function (string $fichier): void {
    if (function_exists('posix_getuid') && posix_getuid() === 0 && @chgrp($fichier, 'www-data')) {
        @chmod($fichier, 0640);
    } else {
        @chmod($fichier, 0644);
    }
};

if (is_file($cible)) {
    $proteger($cible);
    fwrite(STDOUT, "Configuration locale déjà présente : rien à faire.\n");
    exit(0);
}

$base64url = static fn (string $octets): string => rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');

$valeurs = [
    'SECRET' => bin2hex(random_bytes(32)),
    'CLE_ADMIN' => $base64url(random_bytes(32)),
    'BASE_PATH' => $options['base'],
    'URL_PUBLIQUE' => $options['url'],
    'TAILLE_MAX_UPLOAD_MO' => 200,
    'ESPACE_MAX_MO' => 5000,
    'ENV' => $options['env'],
];

$contenu = "<?php\n\n// Configuration locale (développement ou essai du paquet), générée par scripts/config-dev.php. Jamais versionnée.\n\nreturn "
    . var_export($valeurs, true) . ";\n";

if (file_put_contents($cible, $contenu, LOCK_EX) === false) {
    fwrite(STDERR, "Impossible d'écrire la configuration locale.\n");
    exit(1);
}
$proteger($cible);
fwrite(STDOUT, 'Configuration locale créée (secrets aléatoires, ENV = ' . $options['env'] . ").\n");
exit(0);
