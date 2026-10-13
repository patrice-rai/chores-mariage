<?php

declare(strict_types=1);

// Construit le paquet de mise en ligne dist/chores-mariage-vX.Y.Z.zip (docs/deploy/README.md), sans extension zip :
// PharData écrit l'archive, à partir de l'index git (ce qui a été « git add » : rien d'autre).
// Contenu, sous un dossier chores-mariage-vX.Y.Z/ : public/, app/, scripts/ de production, donnees/ (faits du groupe à importer), config.example.php, composer.json sans outillage, README.md,
// docs/deploy/, VERSION et un .htaccess qui refuse tout (au cas où le dossier finirait sous public_html).
// Jamais : stockage/, configuration réelle, tests, node_modules, vendor, .claude, .git, autres docs, outils de
// développement. La liste est contrôlée à la fin : un chemin interdit fait échouer la construction.
// Usage (dans le conteneur) : php scripts/paquet.php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/version.php';

$racine = dirname(__DIR__);
$version = Chores\VERSION;
$nom = 'chores-mariage-v' . $version;
$dist = $racine . '/dist';
$archive = $dist . '/' . $nom . '.zip';

/** Scripts lancés chez l'hébergeur ; les autres (tests, captures, polices, icônes, paquet…) restent au dépôt. */
const SCRIPTS_PRODUCTION = ['migrer.php', 'importer-etat.php', 'nouveau-lien.php', 'sauvegarde.php', 'restaurer.php'];

/** Chemins jamais permis dans l'archive (relatifs au dossier racine du paquet). */
const INTERDITS = [
    '#(^|/)stockage(/|$)#',
    '#(^|/)config\.php$#',
    '#(^|/)chemin-app\.php$#',
    '#^(tests|node_modules|vendor|\.claude|\.git|dist|test-results|tmp)(/|$)#',
    '#^docs/(?!deploy/)#',
    '#(^|/)(e2e-outil|config-dev|paquet|lint|lint-inline|contraste|icones)\.php$#',
    '#\.(sqlite|sqlite3|db|mjs|ts|log)$#',
    '#(^|/)\.(env|git)#',
];

/** Commande git sur le dépôt (sortie brute, null si git échoue ou ne dit rien). */
function git(string $racine, string $arguments): ?string
{
    // safe.directory limité à ce dépôt : le conteneur lance git en root sur un dossier monté depuis Windows.
    $sortie = shell_exec('git -c safe.directory=' . escapeshellarg($racine) . ' -C ' . escapeshellarg($racine) . ' ' . $arguments . ' 2>/dev/null');

    return is_string($sortie) ? $sortie : null;
}

/**
 * Index git (ce qui a été « git add ») sous ces chemins : chemin → objet. Un fichier non suivi (copie d'éditeur,
 * .user.ini, sauvegarde) ne part jamais dans le paquet ; un lien symbolique est refusé (il pourrait viser la
 * configuration).
 * @param list<string> $chemins
 * @return array<string, string>
 */
function index_git(string $racine, array $chemins): array
{
    $sortie = git($racine, 'ls-files -s -z -- ' . implode(' ', array_map('escapeshellarg', $chemins)));
    if ($sortie === null || $sortie === '') {
        fwrite(STDERR, "Paquet refusé : git ne répond pas (le paquet se construit depuis l'index git).\n");
        exit(1);
    }
    $index = [];
    foreach (explode("\0", $sortie) as $ligne) {
        if (preg_match('/^(\d{6}) ([0-9a-f]{40,64}) \d\t(.+)$/sD', $ligne, $m) !== 1) {
            continue;
        }
        if ($m[1] === '120000') {
            fwrite(STDERR, "Paquet refusé : « {$m[3]} » est un lien symbolique.\n");
            exit(1);
        }
        $index[$m[3]] = $m[2];
    }
    ksort($index, SORT_STRING);

    return $index;
}

/** Contenu d'un objet de l'index git (l'état « git add », jamais l'arborescence de travail). */
function objet_git(string $racine, string $objet): string
{
    $contenu = git($racine, 'cat-file blob ' . escapeshellarg($objet));
    if ($contenu === null && trim((string) git($racine, 'cat-file -s ' . escapeshellarg($objet))) !== '0') {
        fwrite(STDERR, "Paquet refusé : objet git illisible.\n");
        exit(1);
    }

    return (string) $contenu;
}

// Contenu : chemin dans le paquet → [objet git, null] ou [null, texte]. Tout vient de l'index git.
$index = index_git($racine, ['public', 'app', 'docs/deploy', 'scripts', 'docs/whatsapp/etat.json', 'docs/distribution.md', 'config.example.php', 'README.md', 'composer.json']);
$contenu = [];
foreach ($index as $fichier => $objet) {
    if (preg_match('#^(public|app|docs/deploy)/#', $fichier) === 1) {
        $contenu[$fichier] = [$objet, null];
    }
}
$autres = ['donnees/etat.json' => 'docs/whatsapp/etat.json', 'donnees/distribution.md' => 'docs/distribution.md', 'config.example.php' => 'config.example.php', 'README.md' => 'README.md'];
foreach (SCRIPTS_PRODUCTION as $script) {
    $autres['scripts/' . $script] = 'scripts/' . $script;
}
foreach ($autres as $dansLePaquet => $depot) {
    if (!isset($index[$depot])) {
        fwrite(STDERR, "Paquet refusé : « {$depot} » n'est pas suivi par git (git add d'abord).\n");
        exit(1);
    }
    $contenu[$dansLePaquet] = [$index[$depot], null];
}
if (trim((string) git($racine, 'diff --name-only -- public app docs/deploy scripts docs/whatsapp/etat.json docs/distribution.md README.md config.example.php composer.json')) !== '') {
    fwrite(STDERR, "Attention : des fichiers du paquet ont des modifications pas encore ajoutées à git (git add) : le paquet prend l'état de l'index.\n");
}
$contenu['VERSION'] = [null, $version . "\n"];
$contenu['.htaccess'] = [null, "# Dossier de l'application (app/, scripts/, donnees/, stockage/, configuration) : jamais servi par le web,\n"
    . "# même s'il finit sous public_html (repli de docs/deploy/README.md). La règle de réécriture double le refus\n"
    . "# sur un serveur qui ne lirait pas « Require ».\n"
    . "Require all denied\n"
    . "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule ^ - [F]\n</IfModule>\n"];

$composer = json_decode(objet_git($racine, $index['composer.json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
unset($composer['require-dev'], $composer['autoload-dev'], $composer['scripts']);
$composer['version'] = $version;
$composer['description'] = 'Site privé « Chorés du mariage » : PHP 8.3 sans framework + SQLite. Aucune dépendance à installer.';
$contenu['composer.json'] = [null, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"];

// Rien d'interdit, rien de manquant.
foreach (array_keys($contenu) as $chemin) {
    foreach (INTERDITS as $motif) {
        if (preg_match($motif, $chemin) === 1) {
            fwrite(STDERR, "Paquet refusé : chemin interdit « {$chemin} ».\n");
            exit(1);
        }
    }
}
foreach (['public/index.php', 'public/.htaccess', 'app/bootstrap.php', 'app/version.php', 'docs/deploy/README.md', 'scripts/migrer.php'] as $requis) {
    if (!isset($contenu[$requis])) {
        fwrite(STDERR, "Paquet refusé : « {$requis} » manque.\n");
        exit(1);
    }
}

if (!is_dir($dist) && !mkdir($dist, 0755, true) && !is_dir($dist)) {
    fwrite(STDERR, "Impossible de créer dist/.\n");
    exit(1);
}
if (is_file($archive)) {
    unlink($archive);
}

try {
    $zip = new PharData($archive, 0, null, Phar::ZIP);
    foreach ($contenu as $chemin => [$source, $texte]) {
        $zip->addFromString($nom . '/' . $chemin, $source !== null ? objet_git($racine, $source) : (string) $texte);
    }
    // Compression de chaque fichier (extension zlib, présente partout) : le zip passe d'environ 800 Ko à 300 Ko.
    $zip->compressFiles(Phar::GZ);
    $zip = null;
} catch (Throwable $erreur) {
    fwrite(STDERR, 'Archive impossible : ' . $erreur->getMessage() . "\n");
    exit(1);
}

// Relecture de l'archive écrite : mêmes chemins, aucun interdit.
$relus = [];
foreach (new RecursiveIteratorIterator(new PharData($archive)) as $entree) {
    $chemin = str_replace('\\', '/', (string) $entree->getPathname());
    $relus[] = substr($chemin, strpos($chemin, $nom . '/') + strlen($nom) + 1);
}
sort($relus, SORT_STRING);
$attendus = array_keys($contenu);
sort($attendus, SORT_STRING);
if ($relus !== $attendus) {
    fwrite(STDERR, "Paquet refusé : l'archive relue ne correspond pas à la liste prévue.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Paquet construit : dist/%s.zip (%d fichiers, %s octets, SHA-256 %s).\n",
    $nom,
    count($relus),
    number_format((float) filesize($archive), 0, ',', ' '),
    hash_file('sha256', $archive)
));
exit(0);
