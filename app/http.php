<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Requête (Contexte), réponse (Reponse), rendu des gabarits et envoi.

/** Ce que l'application sait de la requête courante. */
final class Contexte
{
    private ?PDO $pdo = null;
    /** Jeton CSRF créé pendant cette requête, à poser en cookie avec la réponse. */
    public ?string $nouveauCsrf = null;
    /** @var array{id: int, prenom: string, est_admin: int}|null */
    public ?array $cousin = null;
    /** Tests : un fichier « téléversé » est un simple fichier temporaire (rename au lieu de move_uploaded_file). */
    public bool $televersementSimule = false;

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $cookies
     * @param array<string, mixed> $post
     */
    public function __construct(
        public readonly array $config,
        public readonly string $methode,
        public readonly ?string $chemin,
        public readonly array $cookies = [],
        public readonly array $post = [],
        public readonly string $ip = '0.0.0.0',
        public readonly string $origine = '',
        public readonly string $hote = '',
        public readonly bool $https = false,
        ?PDO $pdo = null,
        public readonly string $secFetchSite = '',
        /** @var array<string, string> paramètres de l'adresse (après « ? ») */
        public readonly array $requete = [],
        /** @var array<string, string> quelques en-têtes utiles, noms en minuscules (range, accept, content-length, content-type) */
        public readonly array $entetes = [],
        /** @var array<string, mixed> fichiers téléversés ($_FILES) */
        public readonly array $fichiers = [],
    ) {
        $this->pdo = $pdo;
    }

    /** @param array<string, mixed> $config */
    public static function depuisGlobales(array $config): self
    {
        // Chemin brut avant « ? », sans décodage : aucune route n'a besoin de « % », et une adresse encodée ne doit
        // pas contourner le masque du journal d'accès (parse_url lirait « //admin » comme un nom d'hôte).
        $chemin = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $chemin = is_string($chemin) && $chemin !== '' ? $chemin : '/';

        return new self(
            $config,
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            retirer_base($chemin, (string) $config['BASE_PATH']),
            array_map('strval', array_filter($_COOKIE, 'is_string')),
            $_POST,
            // Seule l'adresse vue par le serveur compte : un en-tête X-Forwarded-For se falsifie.
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            null,
            strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')),
            array_map('strval', array_filter($_GET, 'is_string')),
            array_filter([
                'range' => (string) ($_SERVER['HTTP_RANGE'] ?? ''),
                'accept' => (string) ($_SERVER['HTTP_ACCEPT'] ?? ''),
                'content-length' => (string) ($_SERVER['CONTENT_LENGTH'] ?? ''),
                'content-type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
            ], static fn (string $v): bool => $v !== ''),
            $_FILES,
        );
    }

    /** Paramètre d'adresse (?nom=valeur), ou null. */
    public function parametre(string $nom): ?string
    {
        $valeur = $this->requete[$nom] ?? null;

        return is_string($valeur) ? $valeur : null;
    }

    public function entete(string $nom): ?string
    {
        return $this->entetes[strtolower($nom)] ?? null;
    }

    /** Thème choisi par la bascule (cookie), ou null : le système décide. */
    public function theme(): ?string
    {
        $theme = $this->cookie(COOKIE_THEME);

        return in_array($theme, THEMES, true) ? $theme : null;
    }

    /**
     * Adresse courante sans BASE_PATH, pour revenir ici après un formulaire. Seuls les paramètres connus sont
     * recopiés : un texte choisi par un tiers ne doit pas se retrouver à côté du jeton CSRF dans la page.
     */
    public function ici(): string
    {
        $chemin = $this->chemin ?? '/';
        $connus = array_filter(
            array_intersect_key($this->requete, array_flip(PARAMETRES_DE_RETOUR)),
            static fn (string $v): bool => preg_match('/^[A-Za-z0-9_-]{1,20}$/D', $v) === 1
        );

        return $connus === [] ? $chemin : $chemin . '?' . http_build_query($connus);
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= connexion(chemin_base_active($this->config));
    }

    /** Ferme la connexion (avant de remplacer le fichier de la base) ; pdo() en rouvre une au besoin. */
    public function fermerBase(): void
    {
        $this->pdo = null;
    }

    public function cookie(string $nom): ?string
    {
        $valeur = $this->cookies[$nom] ?? null;

        return is_string($valeur) && $valeur !== '' ? $valeur : null;
    }

    public function champ(string $nom): ?string
    {
        $valeur = $this->post[$nom] ?? null;

        return is_string($valeur) ? $valeur : null;
    }

    /** Le client demande du JSON (amélioration en JavaScript d'un formulaire). */
    public function veutJson(): bool
    {
        return str_contains((string) $this->entete('accept'), 'application/json');
    }
}

/** Paramètres d'adresse recopiés par Contexte::ici(). */
const PARAMETRES_DE_RETOUR = ['section', 'surligner'];

/** Réponse HTTP complète, construite par les contrôleurs puis envoyée par envoyer(). */
final class Reponse
{
    /** @var array{chemin: string, debut: int, longueur: int}|null portion de fichier envoyée à la place du corps */
    public ?array $fichier = null;

    /**
     * @param array<string, string> $entetes
     * @param list<array{nom: string, valeur: string, duree: int}> $cookies
     */
    public function __construct(
        public int $statut = 200,
        public string $corps = '',
        public array $entetes = [],
        public array $cookies = [],
    ) {
    }

    public static function html(string $corps, int $statut = 200): self
    {
        return new self($statut, $corps, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirection(string $url, int $statut = 303): self
    {
        return new self($statut, '', ['Location' => $url]);
    }

    /** $duree en secondes ; 0 = cookie de session ; négatif = effacement. */
    public function avecCookie(string $nom, string $valeur, int $duree): self
    {
        $this->cookies[] = ['nom' => $nom, 'valeur' => $valeur, 'duree' => $duree];

        return $this;
    }

    public function avecEntete(string $nom, string $valeur): self
    {
        $this->entetes[$nom] = $valeur;

        return $this;
    }
}

/** Chemin de la requête sans BASE_PATH, ou null s'il est hors du site. */
function retirer_base(string $chemin, string $base): ?string
{
    if ($chemin === '' || $chemin[0] !== '/') {
        return null;
    }
    if ($base === '') {
        return $chemin;
    }
    if ($chemin === $base) {
        return '/';
    }
    if (str_starts_with($chemin, $base . '/')) {
        return substr($chemin, strlen($base));
    }

    return null;
}

/** Adresse interne du site (avec BASE_PATH). */
function url(array $config, string $chemin): string
{
    return $config['BASE_PATH'] . $chemin;
}

function e(string|int|float|null $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Dossier public/ (racine web) : à côté de app/ en local, ailleurs chez l'hébergeur (public/chemin-app.php). */
function dossier_public(): string
{
    return defined('CHORES_PUBLIC') ? CHORES_PUBLIC : RACINE . '/public';
}

/** Adresse d'un fichier de public/ avec une empreinte de son contenu (cache long sans risque de version périmée). */
function actif(array $config, string $chemin): string
{
    static $empreintes = [];
    if (!isset($empreintes[$chemin])) {
        $fichier = dossier_public() . $chemin;
        $empreintes[$chemin] = is_file($fichier) ? substr((string) hash_file('sha256', $fichier), 0, 10) : '0';
    }

    return url($config, $chemin) . '?v=' . $empreintes[$chemin];
}

/**
 * Rend une vue de app/vues/ dans le gabarit commun.
 * @param array<string, mixed> $donnees
 */
function rendre(Contexte $c, string $vue, array $donnees, int $statut = 200): Reponse
{
    $donnees['c'] = $c;
    $donnees['config'] = $c->config;
    if (!isset($donnees['contact'])) {
        try {
            $donnees['contact'] = reglage($c->pdo(), 'contact_patrice');
        } catch (\Throwable) {
            $donnees['contact'] = null;
        }
    }
    $contenu = inclure_vue($vue, $donnees);
    $page = inclure_vue('gabarit', $donnees + ['contenu' => $contenu] + DEFAUTS_GABARIT);

    return Reponse::html($page, $statut);
}

/** @param array<string, mixed> $donnees */
function inclure_vue(string $vue, array $donnees): string
{
    if (!preg_match('#^[a-z0-9/-]+$#', $vue)) {
        throw new \InvalidArgumentException('Nom de vue invalide.');
    }
    extract($donnees, EXTR_SKIP);
    ob_start();
    try {
        require __DIR__ . '/vues/' . $vue . '.php';
    } catch (\Throwable $erreur) {
        ob_end_clean();
        throw $erreur;
    }

    return (string) ob_get_clean();
}

/** Options du gabarit commun (app/vues/gabarit.php), remplaçables par chaque page. */
const DEFAUTS_GABARIT = [
    'titre' => 'Chorés du mariage',
    'barre' => null,
    'barreAction' => 'changer',
    'barreRetour' => null,
    'bascule' => true,
    'themeForce' => null,
    'classePage' => '',
    'feuilles' => [],
    'scripts' => [],
    'pied' => true,
    'contact' => null,
];

/** Page d'erreur autonome (n'utilise pas la base). */
function page_erreur(array $config, int $statut, string $titre, string $message): Reponse
{
    $c = new Contexte($config, 'GET', '/');
    $page = inclure_vue('gabarit', [
        'c' => $c,
        'config' => $config,
        'titre' => $titre,
        'contenu' => inclure_vue('erreur', ['config' => $config, 'titre' => $titre, 'message' => $message]),
    ] + DEFAUTS_GABARIT);

    return Reponse::html($page, $statut);
}

/** Réponse JSON (amélioration en JavaScript ; jamais de donnée d'un autre cousin au-delà des pages). */
function reponse_json(array $donnees, int $statut = 200): Reponse
{
    return new Reponse($statut, (string) json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
        'Content-Type' => 'application/json; charset=utf-8',
    ]);
}

/** Envoie la réponse : statut, en-têtes de sécurité, en-têtes propres, cookies, corps. */
function envoyer(Reponse $reponse, array $config): void
{
    if (headers_sent()) {
        echo $reponse->corps;

        return;
    }
    header_remove('X-Powered-By');
    http_response_code($reponse->statut);
    foreach (entetes_securite($config) as $nom => $valeur) {
        header($nom . ': ' . $valeur);
    }
    // Pages privées : jamais rangées dans un cache, même celui du navigateur (le service worker aura le sien).
    $entetes = $reponse->entetes + ['Cache-Control' => 'no-store'];
    foreach ($entetes as $nom => $valeur) {
        header($nom . ': ' . str_replace(["\r", "\n"], '', $valeur));
    }
    foreach ($reponse->cookies as $cookie) {
        setcookie($cookie['nom'], $cookie['duree'] < 0 ? '' : $cookie['valeur'], options_cookie($config, $cookie['duree']));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        return;
    }
    if ($reponse->fichier !== null) {
        envoyer_portion($reponse->fichier['chemin'], $reponse->fichier['debut'], $reponse->fichier['longueur']);

        return;
    }
    echo $reponse->corps;
}

/** Envoie une portion de fichier par morceaux de 64 Ko, sans la charger en mémoire. */
function envoyer_portion(string $chemin, int $debut, int $longueur): void
{
    $flux = fopen($chemin, 'rb');
    if ($flux === false) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fseek($flux, $debut);
    $reste = $longueur;
    while ($reste > 0 && !feof($flux) && connection_status() === CONNECTION_NORMAL) {
        $morceau = fread($flux, min(65536, $reste));
        if ($morceau === false || $morceau === '') {
            break;
        }
        echo $morceau;
        flush();
        $reste -= strlen($morceau);
    }
    fclose($flux);
}

/** Fragment de gabarit de app/vues/composants/ (les données reçoivent aussi $c et $config). */
function composant(Contexte $c, string $nom, array $donnees = []): string
{
    return inclure_vue('composants/' . $nom, $donnees + ['c' => $c, 'config' => $c->config]);
}

/** @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string} */
function options_cookie(array $config, int $duree): array
{
    return [
        'expires' => $duree === 0 ? 0 : ($duree < 0 ? 1 : time() + $duree),
        'path' => $config['BASE_PATH'] === '' ? '/' : $config['BASE_PATH'],
        'secure' => $config['ENV'] !== 'dev',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}
