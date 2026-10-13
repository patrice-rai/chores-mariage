<?php

declare(strict_types=1);

namespace Chores\Controleurs;

use Chores\Contexte;
use Chores\Reponse;

use function Chores\chemin_base_active;
use function Chores\date_sauvegarde;
use function Chores\dossier_sauvegardes;
use function Chores\elaguer_sauvegardes;
use function Chores\journaliser;
use function Chores\liste_sauvegardes;
use function Chores\migrations_en_attente;
use function Chores\migrer;
use function Chores\nom_sauvegarde_valide;
use function Chores\prefixe_sauvegardes;
use function Chores\restaurer_base;
use function Chores\sauvegarder_base;
use function Chores\url;

// Administration, « Base et sauvegardes » (phase 6) : version, mises à jour de la base, sauvegardes et restauration,
// sans SSH. La page ne lit que la table des migrations et le dossier des sauvegardes : elle reste utilisable quand
// une nouvelle version attend ses migrations (le reste de l'administration peut alors échouer).

/** Sauvegardes gardées quand Patrice en fait une depuis l'administration (trois mois au rythme d'une par semaine). */
const SAUVEGARDES_GARDEES = 12;

/** GET /admin/base */
function admin_base(Contexte $c, array $params): Reponse
{
    $base = chemin_base_active($c->config);
    $sauvegardes = liste_sauvegardes(dossier_sauvegardes($base), prefixe_sauvegardes($base));
    $aRestaurer = null;
    foreach ($sauvegardes as $s) {
        if ($s['nom'] === $c->parametre('restaurer')) {
            $aRestaurer = $s;
        }
    }
    $confirmations = [
        'base' => 'Base mise à jour.',
        'base-a-jour' => 'La base était déjà à jour : rien à faire.',
        'sauvegarde' => 'Sauvegarde faite.',
        'restauree' => 'Sauvegarde restaurée ; le lien secret n’a pas changé. La base d’avant est gardée dans sauvegardes/avant-restauration/.',
        'restauree-lien-sauvegarde' => 'Sauvegarde restaurée. La base d’avant n’avait pas de lien lisible : c’est le lien de la sauvegarde qui marche ; crée un nouveau lien s’il avait circulé.',
    ];
    $erreurs = [
        'sauvegarde' => 'La sauvegarde n’a pas pu se faire. Réessaie ; si ça continue, regarde le journal d’erreurs de l’hébergeur.',
        'base' => 'Une mise à jour de la base a échoué : celles d’avant sont gardées, celle qui a échoué n’a rien changé. Fais une sauvegarde, puis réessaie ou passe par le SSH (docs/deploy/mise-a-jour.md).',
        'restauration' => 'La restauration a été refusée : la base actuelle n’a pas changé. La sauvegarde choisie est peut-être abîmée, ou une autre restauration était en cours.',
        'restauration-partielle' => 'La sauvegarde est remise en place, mais une mise à jour de la base a échoué ensuite : touche « Mettre la base à jour » ci-dessous, ou passe par le SSH (php scripts/migrer.php).',
        'inconnue' => 'Cette sauvegarde n’existe plus.',
    ];

    return vue_admin($c, 'base', 'Base et sauvegardes', [
        'enAttente' => migrations_en_attente($c->pdo()),
        'sauvegardes' => array_map(static fn (array $s): array => $s + ['date' => date_sauvegarde($s['nom'])], $sauvegardes),
        'aRestaurer' => $aRestaurer === null ? null : $aRestaurer + ['date' => date_sauvegarde($aRestaurer['nom'])],
        'confirmation' => $confirmations[(string) $c->parametre('ok')] ?? null,
        'erreur' => $erreurs[(string) $c->parametre('erreur')] ?? null,
    ]);
}

/** POST /admin/migrer : applique les migrations en attente (mise à jour sans SSH). */
function admin_migrer(Contexte $c, array $params): Reponse
{
    $pdo = $c->pdo();
    try {
        $appliquees = migrer($pdo);
    } catch (\Throwable $erreur) {
        error_log('[chores] migration refusée depuis l’administration : ' . $erreur->getMessage());

        return Reponse::redirection(url($c->config, '/admin/base?erreur=base'), 303);
    }
    if ($appliquees !== []) {
        journaliser($pdo, 'admin_modif', $c->cousin['id'] ?? null, 'mise à jour de la base : ' . implode(', ', $appliquees));
    }

    return Reponse::redirection(url($c->config, '/admin/base?ok=' . ($appliquees === [] ? 'base-a-jour' : 'base')), 303);
}

/** POST /admin/sauvegarde : copie cohérente de la base dans stockage/sauvegardes/ (téléchargée par hPanel). */
function admin_sauvegarder(Contexte $c, array $params): Reponse
{
    $base = chemin_base_active($c->config);
    try {
        $fichier = sauvegarder_base($base);
        elaguer_sauvegardes(dirname($fichier), prefixe_sauvegardes($base), SAUVEGARDES_GARDEES);
    } catch (\Throwable $erreur) {
        error_log('[chores] sauvegarde refusée : ' . $erreur->getMessage());

        return Reponse::redirection(url($c->config, '/admin/base?erreur=sauvegarde'), 303);
    }
    journaliser($c->pdo(), 'admin_modif', $c->cousin['id'] ?? null, 'sauvegarde ' . basename($fichier));

    return Reponse::redirection(url($c->config, '/admin/base?ok=sauvegarde'), 303);
}

/** POST /admin/restaurer : remet une sauvegarde de la liste à la place de la base (confirmée sur la page). */
function admin_restaurer(Contexte $c, array $params): Reponse
{
    $base = chemin_base_active($c->config);
    $nom = (string) $c->champ('sauvegarde');
    $fichier = dossier_sauvegardes($base) . '/' . $nom;
    if (!nom_sauvegarde_valide($nom, prefixe_sauvegardes($base)) || !is_file($fichier)) {
        return Reponse::redirection(url($c->config, '/admin/base?erreur=inconnue'), 303);
    }
    $cousin = $c->cousin['id'] ?? null;
    // La connexion de cette requête se ferme avant que le fichier de la base soit remplacé.
    $c->fermerBase();
    try {
        $resultat = restaurer_base($fichier, $base);
    } catch (\Chores\RestaurationPartielle $erreur) {
        error_log('[chores] restauration partielle : ' . $erreur->getMessage());

        return Reponse::redirection(url($c->config, '/admin/base?erreur=restauration-partielle'), 303);
    } catch (\Throwable $erreur) {
        error_log('[chores] restauration refusée : ' . $erreur->getMessage());

        return Reponse::redirection(url($c->config, '/admin/base?erreur=restauration'), 303);
    }
    try {
        journaliser($c->pdo(), 'admin_modif', $cousin, 'restauration de ' . $nom);
    } catch (\Throwable $erreur) {
        // La restauration a réussi ; le cousin n'existe peut-être pas dans la base remise : le journal s'en passe.
        error_log('[chores] restauration non journalisée : ' . $erreur->getMessage());
    }

    return Reponse::redirection(url($c->config, '/admin/base?ok=' . ($resultat['lienGarde'] ? 'restauree' : 'restauree-lien-sauvegarde')), 303);
}
