<?php

declare(strict_types=1);

use function Chores\actif;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

/**
 * Squelette commun de toutes les pages.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var string $titre
 * @var string $contenu
 * @var ?string $barre titre de la barre haute (null : pas de barre)
 * @var ?string $barreAction « changer » (bouton Changer), « moi » (lien vers Ma page), « retour » ou null
 * @var ?string $barreRetour adresse du bouton Retour
 * @var bool $bascule bascule de thème dans la barre haute
 * @var ?string $themeForce « sombre » pour le mode répétition (toujours sombre)
 * @var string $classePage classe posée sur <body>
 * @var list<string> $feuilles feuilles en plus de jetons, base et composants
 * @var list<string> $scripts modules en plus de site.js
 * @var bool $pied pied de page « Un souci ? »
 */
$titrePage = $titre === 'Chorés du mariage' ? $titre : $titre . ' — Chorés du mariage';
// Sans choix enregistré, aucun data-theme : le système décide (prefers-color-scheme), sans éclair au chargement.
$theme = $themeForce ?? $c->theme();
$polices = ['/polices/yeseva-one-latin-400-normal-v5-3-0.woff2', '/polices/nunito-sans-latin-wght-normal-v5-3-0.woff2'];
?>
<!doctype html>
<html lang="fr"<?= $theme !== null ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= e($titrePage) ?></title>
<link rel="manifest" href="<?= e(url($config, '/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(url($config, '/icones/icone-192.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url($config, '/icones/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-title" content="Chorés">
<?php foreach ($polices as $police): ?>
<link rel="preload" href="<?= e(url($config, $police)) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
<link rel="stylesheet" href="<?= e(actif($config, '/css/jetons.css')) ?>">
<link rel="stylesheet" href="<?= e(actif($config, '/css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(actif($config, '/css/composants.css')) ?>">
<link rel="stylesheet" href="<?= e(actif($config, '/css/pages.css')) ?>">
<?php foreach ($feuilles as $feuille): ?>
<link rel="stylesheet" href="<?= e(actif($config, $feuille)) ?>">
<?php endforeach; ?>
<script type="module" src="<?= e(actif($config, '/js/site.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= e(actif($config, $script)) ?>"></script>
<?php endforeach; ?>
</head>
<body<?= $classePage !== '' ? ' class="' . e($classePage) . '"' : '' ?>>
<a class="evitement" href="#contenu">Aller au contenu</a>
<div class="banniere-hors-ligne" role="status" data-hors-ligne></div>
<?php /* Rempli par site.js quand une nouvelle version du site (service worker) prend la main : « Recharger ». */ ?>
<div class="nouvelle-version-zone" role="status" data-nouvelle-version></div>
<?php if ($barre !== null): ?>
<?= composant($c, 'barre-haute', ['titre' => $barre, 'action' => $barreAction, 'retour' => $barreRetour, 'bascule' => $bascule && $themeForce === null]) ?>
<?php endif; ?>
<main id="contenu" class="page" tabindex="-1">
<?= $contenu ?>
</main>
<?php if ($pied): ?>
<footer class="pied">
  <p>Un souci ? Écris à Patrice <?= e(isset($contact) && $contact !== '' ? $contact : '[contact]') ?></p>
</footer>
<?php endif; ?>
</body>
</html>
