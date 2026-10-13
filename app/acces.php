<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Accès sans compte (spec § 6) : un seul lien secret (haché en base), un cookie d'accès lié à la version du lien,
// un cookie prénom signé, un cookie d'administration posé par la clé de config.php, un jeton CSRF par cookie
// et champ caché signé. Toutes les comparaisons se font en temps constant.

const COOKIE_ACCES = 'cm_acces';
const COOKIE_PRENOM = 'cm_prenom';
const COOKIE_ADMIN = 'cm_admin';
const COOKIE_CSRF = 'cm_csrf';
/** Thème choisi par la bascule (aucune donnée personnelle : « clair » ou « sombre »). */
const COOKIE_THEME = 'cm_theme';
const THEMES = ['clair', 'sombre'];
const DUREE_COOKIE = 400 * 86400;
const CHAMP_CSRF = 'csrf';

function base64url(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

/** Signature HMAC-SHA256 avec séparation des usages (« acces », « prenom », « admin », « csrf »). */
function signer(string $secret, string $usage, string $valeur): string
{
    return base64url(hash_hmac('sha256', $usage . ':' . $valeur, $secret, true));
}

// --- Lien secret -------------------------------------------------------------------------------------------

/**
 * Crée un nouveau jeton, range son empreinte, incrémente la version (tous les cookies d'accès tombent).
 * Avec $versionAttendue : rien n'est fait (null) si le lien a changé entre-temps (formulaire renvoyé).
 */
function nouveau_jeton(PDO $pdo, ?int $versionAttendue = null): ?string
{
    $jeton = base64url(random_bytes(32));
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $actuelle = (int) (reglage($pdo, 'jeton_version') ?? '0');
        if ($versionAttendue !== null && $versionAttendue !== $actuelle) {
            $pdo->exec('ROLLBACK');

            return null;
        }
        $version = $actuelle + 1;
        poser_reglage($pdo, 'jeton_hash', hash('sha256', $jeton));
        poser_reglage($pdo, 'jeton_version', (string) $version);
        poser_reglage($pdo, 'jeton_cree_le', maintenant_utc());
        $pdo->exec('COMMIT');
    } catch (\Throwable $erreur) {
        $pdo->exec('ROLLBACK');
        throw $erreur;
    }

    return $jeton;
}

function jeton_valide(PDO $pdo, string $jeton): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton)) {
        return false;
    }
    $attendu = reglage($pdo, 'jeton_hash');

    return $attendu !== null && hash_equals($attendu, hash('sha256', $jeton));
}

function version_lien(PDO $pdo): int
{
    return (int) (reglage($pdo, 'jeton_version') ?? '0');
}

/** Lien complet à transmettre aux cousins. */
function lien_complet(array $config, string $jeton, string $hote = '', bool $https = false): string
{
    $origine = $config['URL_PUBLIQUE'];
    if ($origine === '') {
        $origine = preg_match('/^[A-Za-z0-9.-]+(:\d{1,5})?$/', $hote)
            ? ($https || $config['ENV'] === 'production' ? 'https://' : 'http://') . $hote
            : 'https://[domaine]';
    }

    return $origine . url($config, '/e/' . $jeton);
}

// --- Cookie d'accès ----------------------------------------------------------------------------------------

function valeur_cookie_acces(string $secret, int $version): string
{
    return $version . '.' . signer($secret, 'acces', (string) $version);
}

function cookie_acces_valide(string $secret, int $versionCourante, ?string $valeur): bool
{
    if ($valeur === null || $versionCourante < 1) {
        return false;
    }

    return hash_equals(valeur_cookie_acces($secret, $versionCourante), $valeur);
}

// --- Cookie prénom -----------------------------------------------------------------------------------------

/** Lié à la version du lien : renouveler le lien efface aussi les prénoms choisis (spec § 6). */
function valeur_cookie_prenom(string $secret, int $cousinId, int $version): string
{
    return $cousinId . '.' . signer($secret, 'prenom', $cousinId . ':' . $version);
}

/** Identifiant du cousin si la signature est bonne pour la version courante du lien, sinon null. */
function lire_cookie_prenom(string $secret, ?string $valeur, int $version): ?int
{
    if ($valeur === null || $version < 1 || !preg_match('/^([1-9][0-9]{0,8})\.([A-Za-z0-9_-]{43})$/', $valeur, $m)) {
        return null;
    }

    return hash_equals(valeur_cookie_prenom($secret, (int) $m[1], $version), $valeur) ? (int) $m[1] : null;
}

/** @return array{id: int, prenom: string, est_admin: int}|null */
function cousin_actif(PDO $pdo, int $id): ?array
{
    $requete = $pdo->prepare('SELECT id, prenom, est_admin FROM cousins WHERE id = ? AND actif = 1');
    $requete->execute([$id]);
    $ligne = $requete->fetch();

    return $ligne === false ? null : ['id' => (int) $ligne['id'], 'prenom' => (string) $ligne['prenom'], 'est_admin' => (int) $ligne['est_admin']];
}

/** @return list<array{id: int, prenom: string}> */
function cousins_actifs(PDO $pdo): array
{
    return array_map(
        static fn (array $l): array => ['id' => (int) $l['id'], 'prenom' => (string) $l['prenom']],
        $pdo->query('SELECT id, prenom FROM cousins WHERE actif = 1 ORDER BY ordre, prenom')->fetchAll()
    );
}

// --- Cookie d'administration -------------------------------------------------------------------------------

/** Lié à la clé : changer CLE_ADMIN dans config.php invalide tous les appareils. */
function valeur_cookie_admin(string $secret, string $cleAdmin): string
{
    return signer($secret, 'admin', hash('sha256', $cleAdmin));
}

function cle_admin_valide(string $cleAdmin, string $proposee): bool
{
    return $cleAdmin !== '' && hash_equals($cleAdmin, $proposee);
}

function cookie_admin_valide(string $secret, string $cleAdmin, ?string $valeur): bool
{
    return $cleAdmin !== '' && $valeur !== null && hash_equals(valeur_cookie_admin($secret, $cleAdmin), $valeur);
}

// --- CSRF (double soumission signée) -----------------------------------------------------------------------

function jeton_csrf_bien_forme(?string $jeton): bool
{
    return $jeton !== null && preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton) === 1;
}

function champ_csrf_attendu(string $secret, string $jetonCookie): string
{
    return signer($secret, 'csrf', $jetonCookie);
}

function csrf_valide(string $secret, ?string $jetonCookie, ?string $champ): bool
{
    if (!jeton_csrf_bien_forme($jetonCookie) || $champ === null) {
        return false;
    }

    return hash_equals(champ_csrf_attendu($secret, (string) $jetonCookie), $champ);
}

/** Valeur du champ caché à mettre dans un formulaire (crée le jeton de la visite s'il manque). */
function champ_csrf(Contexte $c): string
{
    $jeton = $c->nouveauCsrf ?? $c->cookie(COOKIE_CSRF);
    if (!jeton_csrf_bien_forme($jeton)) {
        $jeton = $c->nouveauCsrf = base64url(random_bytes(32));
    }

    return champ_csrf_attendu($c->config['SECRET'], (string) $jeton);
}

/**
 * Une requête venue d'un autre site est refusée, même avec un jeton. Avec `Referrer-Policy: no-referrer`, les
 * navigateurs envoient `Origin: null` sur nos propres formulaires : l'en-tête `Sec-Fetch-Site` (posé par le
 * navigateur, jamais par une page) tranche alors, et le jeton CSRF reste exigé dans tous les cas.
 */
function origine_acceptable(Contexte $c): bool
{
    if ($c->secFetchSite !== '' && !in_array($c->secFetchSite, ['same-origin', 'none'], true)) {
        return false;
    }
    if ($c->origine === '' || $c->origine === 'null') {
        return true;
    }
    $hote = parse_url($c->origine, PHP_URL_HOST);
    $port = parse_url($c->origine, PHP_URL_PORT);
    if (!is_string($hote)) {
        return false;
    }
    $attendu = strtolower($c->hote);
    $propose = strtolower($hote) . (is_int($port) ? ':' . $port : '');

    return $propose === $attendu || (!is_int($port) && $propose === preg_replace('/:(80|443)$/', '', $attendu));
}

// --- Contrôle par niveau de route --------------------------------------------------------------------------

/**
 * Vérifie le niveau d'accès d'une route. Renvoie null si la requête peut continuer, sinon la réponse à rendre.
 * Niveaux : public, acces (lien), prenom (lien + prénom), admin (lien + cookie d'administration),
 * installation (base neuve seulement).
 */
function controler_acces(Contexte $c, string $niveau): ?Reponse
{
    if ($niveau === 'public') {
        return null;
    }
    // Installation sans SSH : n'existe que sur une base neuve (sinon 404, avant même le contrôle CSRF).
    if ($niveau === 'installation') {
        return installation_possible($c) ? null : page_introuvable($c);
    }
    // Gabarits et démonstration (phase 3) : libres en développement (le prénom éventuel sert à surligner « moi »),
    // réservés à Patrice ailleurs.
    if ($niveau === 'gabarits') {
        // Libre seulement en développement ET sur le poste lui-même : un ENV « dev » oublié chez l'hébergeur
        // n'ouvre rien.
        if ($c->config['ENV'] !== 'dev' || !hote_local($c->hote)) {
            return controler_acces($c, 'admin');
        }
        controler_acces($c, 'acces');

        return null;
    }
    $secret = $c->config['SECRET'];
    $version = version_lien($c->pdo());
    $acces = cookie_acces_valide($secret, $version, $c->cookie(COOKIE_ACCES));
    // Accès libre : sans cookie valable, on en pose un pour la version courante du lien (il en faut un : le prénom
    // y est lié). L'administration garde sa clé.
    if (!$acces && $c->config['ACCES_LIBRE'] && $version >= 1) {
        $acces = true;
        $c->poserCookieAcces = true;
    }

    $id = lire_cookie_prenom($secret, $c->cookie(COOKIE_PRENOM), $version);
    if ($acces && $id !== null) {
        $c->cousin = cousin_actif($c->pdo(), $id);
    }

    if ($niveau === 'admin') {
        return $acces && cookie_admin_valide($secret, $c->config['CLE_ADMIN'], $c->cookie(COOKIE_ADMIN))
            ? null
            : page_introuvable($c);
    }
    if (!$acces) {
        return Reponse::redirection(url($c->config, '/lien'), 302);
    }
    if ($niveau === 'prenom' && $c->cousin === null) {
        return Reponse::redirection(url($c->config, '/'), 302);
    }

    return null;
}

/** Niveaux dont l'existence ne doit pas se deviner sans le cookie d'administration. */
function niveaux_reserves(Contexte $c): array
{
    return $c->config['ENV'] === 'dev' && hote_local($c->hote) ? ['admin', 'installation'] : ['admin', 'gabarits', 'installation'];
}

/** 127.0.0.1, localhost ou ::1, avec ou sans port. */
function hote_local(string $hote): bool
{
    return preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(:\d{1,5})?$/D', strtolower($hote)) === 1;
}

function page_introuvable(Contexte $c): Reponse
{
    return page_erreur($c->config, 404, 'Page introuvable', 'Cette page n’existe pas (ou plus).');
}
