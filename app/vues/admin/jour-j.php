<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Jour J en administration (non dessiné) : lignes de la check-list (ajouter, renommer, monter, descendre, supprimer
 * avec confirmation en page) ; l'ordre de passage et « qui entre, d'où, musique » se règlent dans chaque chanson.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{id: int, libelle: string, fait: bool, par: ?string}> $lignes
 * @var list<array<string, mixed>> $ordre
 * @var ?array<string, mixed> $aSupprimer
 * @var array<string, string> $erreurs
 * @var array<string, string> $saisie
 * @var ?string $confirmation
 */
$action = url($config, '/admin/jour-j');
$csrf = '<input type="hidden" name="' . e(CHAMP_CSRF) . '" value="' . e(champ_csrf($c)) . '">';
?>
<div class="contenu">
  <h1 class="titre-ecran">Jour J</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <section class="bloc" aria-labelledby="ordre-admin">
    <h2 id="ordre-admin">Ordre de passage</h2>
<?php if ($ordre === []): ?>
    <p>Aucune chanson n’a d’ordre de passage : les cousins lisent « L’ordre de passage n’est pas encore fixé ».</p>
<?php else: ?>
    <ol>
<?php foreach ($ordre as $ch): ?>
      <li><a class="admin__lien" href="<?= e(url($config, '/admin/chansons/' . $ch['slug'])) ?>"><?= e($ch['titre']) ?></a> (numéro <?= e($ch['ordre_passage']) ?>)</li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <p>L’ordre, « qui entre », « d’où » et « qui lance la musique » se règlent dans la fiche de chaque chanson.</p>
    <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons')) ?>">Voir les chansons</a></p>
  </section>

  <section class="bloc" aria-labelledby="checklist-admin">
    <h2 id="checklist-admin">Check-list</h2>
    <?= composant($c, 'resume-erreurs', ['erreurs' => $erreurs, 'titre' => 'La check-list n’a pas changé :']) ?>
<?php if ($aSupprimer !== null): ?>
    <div class="admin__confirmation" role="alert" aria-labelledby="confirmer-ligne">
      <p id="confirmer-ligne"><strong>Supprimer la ligne « <?= e($aSupprimer['libelle']) ?> » ?</strong> Elle disparaît de la check-list de tout le monde.</p>
      <form class="admin__actions" method="post" action="<?= e($action) ?>">
        <?= $csrf ?>
        <input type="hidden" name="id" value="<?= e($aSupprimer['id']) ?>">
        <button class="bouton bouton--plein" type="submit" name="action" value="supprimer">Oui, supprimer la ligne</button>
        <a class="bouton bouton--contour" href="<?= e($action) ?>">Non, la garder</a>
      </form>
    </div>
<?php endif; ?>
<?php if ($lignes === []): ?>
    <p>La check-list est vide.</p>
<?php endif; ?>
    <ol class="admin__liste">
<?php foreach ($lignes as $i => $ligne): ?>
<?php $id = 'ligne-' . $ligne['id']; ?>
      <li class="admin__ligne admin__ligne--check">
        <form class="formulaire admin__formulaire-ligne" method="post" action="<?= e($action) ?>" novalidate>
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= e($ligne['id']) ?>">
          <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'libelle', 'id' => $id, 'libelle' => 'Ligne ' . ($i + 1) . ($ligne['fait'] ? ' (fait' . ($ligne['par'] !== null ? ' par ' . $ligne['par'] : '') . ')' : ' (à faire)'), 'requis' => true, 'valeur' => $saisie[$id] ?? $ligne['libelle'], 'erreur' => $erreurs[$id] ?? null, 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off']]) ?>
          <div class="admin__actions">
            <button class="bouton bouton--plein" type="submit" name="action" value="renommer">Renommer<span class="visuellement-cache"> la ligne <?= e($i + 1) ?></span></button>
<?php if ($i > 0): ?>
            <button class="bouton bouton--contour" type="submit" name="action" value="monter">Monter<span class="visuellement-cache"> la ligne <?= e($i + 1) ?></span></button>
<?php endif; ?>
<?php if ($i < count($lignes) - 1): ?>
            <button class="bouton bouton--contour" type="submit" name="action" value="descendre">Descendre<span class="visuellement-cache"> la ligne <?= e($i + 1) ?></span></button>
<?php endif; ?>
            <a class="bouton bouton--contour" href="<?= e($action . '?supprimer=' . $ligne['id'] . '#checklist-admin') ?>">Supprimer<span class="visuellement-cache"> la ligne <?= e($i + 1) ?></span></a>
          </div>
        </form>
      </li>
<?php endforeach; ?>
    </ol>
    <form class="formulaire" method="post" action="<?= e($action) ?>" novalidate>
      <?= $csrf ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'libelle', 'id' => 'nouvelle-ligne', 'libelle' => 'Nouvelle ligne', 'requis' => true, 'valeur' => $saisie['nouvelle'] ?? '', 'erreur' => $erreurs['nouvelle-ligne'] ?? null, 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off']]) ?>
      <button class="bouton bouton--plein" type="submit" name="action" value="ajouter">Ajouter la ligne</button>
    </form>
  </section>
</div>
