<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\cle_admin_valide;
use function Chores\debit_autorise;
use function Chores\fichiers_des_faits;
use function Chores\importer_etat;
use function Chores\journaliser;
use function Chores\lien_complet;
use function Chores\migrer;
use function Chores\nouveau_jeton;
use function Chores\page_introuvable;
use function Chores\rendre;
use function Chores\reponse_trop_d_essais;
use function Chores\valeur_cookie_acces;
use function Chores\valeur_cookie_admin;
use function Chores\version_lien;

use const Chores\COOKIE_ACCES;
use const Chores\COOKIE_ADMIN;
use const Chores\DUREE_COOKIE;

// Installation sans SSH (docs/deploy/README.md § 9) : sur une base neuve seulement (niveau « installation » :
// 404 dès qu'un lien existe, avant même le contrôle CSRF), la clé d'administration tapée dans un champ (jamais dans
// l'adresse, qui finirait dans les journaux de l'hébergeur) prépare la base, importe les faits du groupe et crée le
// premier lien.

/** GET /installer */
function formulaire_installation(Contexte $c, array $params): Reponse
{
    return vue_installation($c, []);
}

/** POST /installer */
function installer(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    // Les tables d'abord (le débit et le journal en ont besoin) : sans effet sur une base déjà préparée, et
    // protégé contre deux envois simultanés (verrou d'écriture dans migrer()).
    try {
        migrer($pdo);
    } catch (\Throwable $erreur) {
        error_log('[chores] préparation de la base refusée à l’installation : ' . $erreur->getMessage());

        return vue_installation($c, ['installation-base' => 'La base n’a pas pu être préparée (dossier stockage/ absent ou sans droit d’écriture ?). Vérifie l’étape 8 de la procédure, puis réessaie.'], 500);
    }
    if (!debit_autorise($pdo, $c->config['SECRET'], 'entree', $c->ip)) {
        return reponse_trop_d_essais($c, 'entree');
    }
    $cle = trim((string) $c->champ('cle'));
    if ($cle === '') {
        return vue_installation($c, ['installation-cle' => 'Clé d’administration : colle la valeur de CLE_ADMIN du fichier de configuration.'], 422);
    }
    if (!cle_admin_valide($c->config['CLE_ADMIN'], $cle)) {
        journaliser($pdo, 'refus_jeton', null, 'clé d’installation');

        return vue_installation($c, ['installation-cle' => 'Clé d’administration : ce n’est pas la bonne. Recopie la valeur de CLE_ADMIN du fichier de configuration, sans les guillemets.'], 422);
    }

    $faits = fichiers_des_faits();
    $compte = null;
    if ($faits !== null) {
        try {
            $compte = importer_etat($pdo, (string) file_get_contents($faits[0]), (string) file_get_contents($faits[1]));
        } catch (\Throwable $erreur) {
            error_log('[chores] import refusé à l’installation : ' . $erreur->getMessage());

            return vue_installation($c, ['installation-base' => 'Les faits du groupe (dossier donnees/) sont illisibles ou incohérents : rien n’a été gardé. Renvoie le dossier donnees/ du paquet, puis réessaie.'], 500);
        }
        journaliser($pdo, 'admin_modif', null, 'import des faits du groupe (installation)');
    }
    $jeton = nouveau_jeton($pdo, 0);
    if ($jeton === null) {
        // Une autre installation vient d'aboutir : ce formulaire n'a plus lieu d'être.
        return page_introuvable($c);
    }
    $version = version_lien($pdo);
    journaliser($pdo, 'admin_lien', null, 'version ' . $version . ' (installation)');

    return rendre($c, 'installer', [
        'titre' => 'Site installé',
        'lien' => lien_complet($c->config, $jeton, $c->hote, $c->https),
        'compte' => $compte,
        'faitsAbsents' => $faits === null,
        'erreurs' => [],
    ])->avecEntete('Cache-Control', 'no-store')
        ->avecCookie(COOKIE_ACCES, valeur_cookie_acces($c->config['SECRET'], $version), DUREE_COOKIE)
        ->avecCookie(COOKIE_ADMIN, valeur_cookie_admin($c->config['SECRET'], $c->config['CLE_ADMIN']), DUREE_COOKIE);
}

/** @param array<string, string> $erreurs « installation-cle » (au champ) ou « installation-base » (générale) */
function vue_installation(Contexte $c, array $erreurs, int $statut = 200): Reponse
{
    return rendre($c, 'installer', [
        'titre' => 'Installation',
        'lien' => null,
        'compte' => null,
        'faitsAbsents' => fichiers_des_faits() === null,
        'erreurs' => $erreurs,
    ], $statut)->avecEntete('Cache-Control', 'no-store');
}
