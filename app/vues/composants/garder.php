<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\format_octets;

/**
 * « Garder sur mon téléphone » (spec § 8) : télécharge la vidéo entière dans le cache du service worker, avec sa
 * progression, pour la lire sans réseau ; « Retirer de mon téléphone » l'efface. Rendu caché : public/js/pwa.js ne le
 * montre que si un service worker contrôle la page (sans lui, rien ne serait servi hors ligne).
 * @var string $url adresse exacte de la vidéo (/m/{id}?v=…)
 * @var int $taille octets
 * @var string $titre ce que la page « Hors ligne » écrira (« Kanimaa », « Vidéo d'Alex · Section A »)
 * @var string $page adresse de la page à rouvrir hors ligne
 * @var string $id identifiant unique
 */
?>
<div class="garder" id="<?= e($id) ?>" data-garder data-url="<?= e($url) ?>" data-taille="<?= e($taille) ?>" data-titre="<?= e($titre) ?>" data-page="<?= e($page) ?>" hidden>
  <button class="bouton bouton--contour garder__bouton" type="button" data-garder-action>Garder sur mon téléphone <span class="garder__poids">(<?= e(format_octets($taille)) ?>)</span></button>
  <progress class="garder__progression" max="100" value="0" aria-label="Téléchargement sur ton téléphone" data-garder-progression hidden>0 %</progress>
  <p class="garder__etat" role="status" data-garder-etat></p>
  <details class="garder__conseil">
    <summary class="garder__resume">Pour que tes vidéos restent</summary>
    <p>Ajoute le site à ton écran d’accueil : sans ça, ton téléphone peut effacer les vidéos gardées au bout de quelques jours sans visite.</p>
    <p><strong>iPhone :</strong> dans Safari, touche le bouton Partager (le carré avec une flèche), puis « Sur l’écran d’accueil ». Ensuite, ouvre le site depuis l’icône (il te redemandera peut-être le lien de Patrice) et garde tes vidéos depuis là : ce que tu gardes dans Safari ne passe pas dans l’icône.</p>
    <p><strong>Android :</strong> dans Chrome, touche le menu (les trois points), puis « Ajouter à l’écran d’accueil » ou « Installer l’application ».</p>
  </details>
</div>
