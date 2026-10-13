<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\format_octets;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\VERSION;

/**
 * Base et sauvegardes (non dessinée : mêmes composants que l'administration) : version, mises à jour de la base,
 * sauvegardes (faire, lister, restaurer après confirmation en page). Les sauvegardes se téléchargent par le
 * gestionnaire de fichiers de l'hébergeur, jamais par le site.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<string> $enAttente migrations pas encore appliquées
 * @var list<array{nom: string, taille: int, quand: int, date: ?string}> $sauvegardes la plus récente d'abord
 * @var ?array{nom: string, taille: int, quand: int, date: ?string} $aRestaurer sauvegarde dont on confirme la restauration
 * @var ?string $confirmation
 * @var ?string $erreur
 */
$libelle = static fn (array $s): string => $s['date'] !== null ? 'du ' . $s['date'] : $s['nom'];
?>
<div class="contenu">
  <h1 class="titre-ecran">Base et sauvegardes</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($erreur !== null): ?>
  <p class="avertissement" role="alert"><span class="champ__erreur-mot">Erreur :</span> <?= e($erreur) ?></p>
<?php endif; ?>

<?php if ($aRestaurer !== null): ?>
  <div class="admin__confirmation" role="region" aria-labelledby="confirmer-restauration" id="confirmation" tabindex="-1" data-resume-serveur>
    <h2 id="confirmer-restauration">Restaurer la sauvegarde <?= e($libelle($aRestaurer)) ?>&nbsp;?</h2>
    <p>La base actuelle est remplacée (elle est d’abord copiée dans <code>sauvegardes/avant-restauration/</code>). Les niveaux, présences et commentaires postés depuis seront perdus, ce qui a été supprimé depuis revient ; le lien secret et les vidéos ne changent pas (une vidéo envoyée depuis reste sur le disque sans apparaître). Le site répond « maintenance » quelques secondes.</p>
    <form class="admin__actions" method="post" action="<?= e(url($config, '/admin/restaurer')) ?>">
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <input type="hidden" name="sauvegarde" value="<?= e($aRestaurer['nom']) ?>">
      <button class="bouton bouton--plein" type="submit">Oui, restaurer</button>
      <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/base')) ?>">Non, revenir</a>
    </form>
  </div>
<?php endif; ?>

  <section class="bloc" aria-labelledby="titre-version">
    <h2 id="titre-version">Version et base</h2>
    <p>Version du site : <?= e(VERSION) ?>.</p>
<?php if ($enAttente === []): ?>
    <p>Base : à jour.</p>
<?php else: ?>
    <p>Base : <?= e(count($enAttente)) ?> mise(s) à jour en attente (<?= e(implode(', ', $enAttente)) ?>). Fais d’abord une sauvegarde.</p>
    <form method="post" action="<?= e(url($config, '/admin/migrer')) ?>">
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <p><button class="bouton bouton--plein" type="submit">Mettre la base à jour</button></p>
    </form>
<?php endif; ?>
  </section>

  <section class="bloc" aria-labelledby="titre-sauvegardes">
    <h2 id="titre-sauvegardes">Sauvegardes</h2>
    <p>Une sauvegarde copie la base (cousins, niveaux, placements, répétitions, commentaires, journal), sans les vidéos. Les <?= e(Chores\Controleurs\SAUVEGARDES_GARDEES) ?> plus récentes sont gardées dans <code>stockage/sauvegardes/</code> ; télécharge-les par le gestionnaire de fichiers de l’hébergeur (<code>docs/deploy/sauvegardes.md</code>).</p>
    <form method="post" action="<?= e(url($config, '/admin/sauvegarde')) ?>">
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <p><button class="bouton bouton--plein" type="submit">Faire une sauvegarde maintenant</button></p>
    </form>
<?php if ($sauvegardes === []): ?>
    <p>Aucune sauvegarde pour l’instant.</p>
<?php else: ?>
    <ul class="admin__sauvegardes">
<?php foreach ($sauvegardes as $s): ?>
      <li>
        <p>Sauvegarde <?= e($libelle($s)) ?> · <?= e(format_octets($s['taille'])) ?> · <code><?= e($s['nom']) ?></code></p>
        <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin/base?restaurer=' . rawurlencode($s['nom']) . '#confirmation')) ?>">Restaurer<span class="visuellement-cache"> la sauvegarde <?= e($libelle($s)) ?></span></a></p>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
</div>
