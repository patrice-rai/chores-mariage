<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\non_places;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Placements d'une chanson (administration) : une section à la fois, un point par danseur, positions x et y de 0 à
 * 100 (gauche → droite, fond → public ; vides : non placé), flèches d'un pas de 5, « Copier la section précédente ».
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $chanson
 * @var list<array{id: int, nom: string}> $sections
 * @var int $courante
 * @var list<array{cousin_id: int, prenom: string, statut: string}> $distribution
 * @var list<array{cousin_id: int, prenom: string, x: int, y: int}> $points
 * @var array<int, string> $erreurs par cousin
 * @var array<int, array{x: string, y: string}> $saisies
 * @var ?string $confirmation
 */
$slug = (string) $chanson['slug'];
$page = url($config, '/admin/chansons/' . $slug . '/placements');
$parCousin = [];
foreach ($points as $p) {
    $parCousin[$p['cousin_id']] = $p;
}
$fleches = ['gauche' => ['←', 'à gauche'], 'droite' => ['→', 'à droite'], 'fond' => ['↑', 'vers le fond'], 'public' => ['↓', 'vers le public']];
$copie = $erreurs['copie'] ?? null;
unset($erreurs['copie']);
$resume = [];
foreach ($erreurs as $cousinId => $message) {
    $resume['x-' . $cousinId] = $message;
}
?>
<div class="contenu admin">
  <h1 class="titre-ecran">Placements de <?= e($chanson['titre']) ?></h1>
  <nav class="admin__navigation" aria-label="Cette chanson">
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons/' . $slug)) ?>">Fiche de la chanson</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons/' . $slug . '/sections')) ?>">Sections</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/placements/' . $slug)) ?>">Voir la page des cousins</a>
  </nav>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($copie !== null): ?>
  <p class="avertissement" role="alert"><?= e($copie) ?></p>
<?php endif; ?>
<?php if ($sections === []): ?>
  <p>Pose d’abord les sections : les placements se font section par section.</p>
<?php elseif ($distribution === []): ?>
  <p>Personne ne danse encore sur cette chanson : choisis les danseurs dans la fiche.</p>
<?php else: ?>
<?php $section = $sections[$courante]; ?>
  <form class="admin__choix-section" method="get" action="<?= e($page) ?>">
    <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'section', 'id' => 'choix-section', 'libelle' => 'Section', 'requis' => true, 'options' => array_combine(range(1, count($sections)), array_map(static fn (int $i, array $s): string => ($i + 1) . '. ' . $s['nom'], array_keys($sections), $sections)), 'valeur' => (string) ($courante + 1)]) ?>
    <button class="bouton bouton--secondaire" type="submit">Afficher</button>
  </form>

  <?= composant($c, 'mini-scene', ['titre' => 'Aperçu', 'niveauTitre' => 2,'section' => $section['nom'], 'points' => $points, 'moi' => '', 'jeDanse' => false, 'nonPlaces' => non_places($distribution, $points)]) ?>

  <form class="formulaire" id="placements-formulaire" method="post" action="<?= e($page) ?>" novalidate>
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <input type="hidden" name="section_id" value="<?= e($section['id']) ?>">
    <?= composant($c, 'resume-erreurs', ['erreurs' => $resume]) ?>
    <p class="champ__aide">x : de 0 (côté jardin, à gauche) à 100 (côté cour, à droite). y : de 0 (au fond) à 100 (devant le public). Les deux vides : non placé. Une flèche enregistre tout le formulaire et déplace d’un pas de 5.</p>
    <?php /* Premier bouton du formulaire : la touche Entrée dans un champ enregistre, elle ne déplace personne. */ ?>
    <button class="bouton bouton--plein" type="submit" name="action" value="enregistrer">Enregistrer les placements</button>
<?php foreach ($distribution as $d): ?>
<?php
    $id = $d['cousin_id'];
    $p = $parCousin[$id] ?? null;
    $x = $saisies[$id]['x'] ?? ($p !== null ? (string) $p['x'] : '');
    $y = $saisies[$id]['y'] ?? ($p !== null ? (string) $p['y'] : '');
?>
    <fieldset class="admin__point">
      <legend class="champ__etiquette"><?= e($d['prenom']) ?><?= $d['statut'] === 'en_attente' ? ' (en attente)' : '' ?><?= $p === null && !isset($saisies[$id]) ? ' : non placé' : '' ?></legend>
<?php if (isset($erreurs[$id])): ?>
      <p class="champ__erreur" id="erreur-<?= e($id) ?>"><span class="champ__erreur-mot">Erreur :</span> <?= e($erreurs[$id]) ?></p>
<?php endif; ?>
      <div class="admin__xy">
        <div class="champ">
          <label class="champ__etiquette" for="x-<?= e($id) ?>">x <span class="visuellement-cache">pour <?= e($d['prenom']) ?></span></label>
          <input class="champ__zone" type="text" inputmode="numeric" maxlength="3" autocomplete="off" id="x-<?= e($id) ?>" name="x_<?= e($id) ?>" value="<?= e($x) ?>"<?= isset($erreurs[$id]) ? ' aria-invalid="true" aria-describedby="erreur-' . e($id) . '"' : '' ?>>
        </div>
        <div class="champ">
          <label class="champ__etiquette" for="y-<?= e($id) ?>">y <span class="visuellement-cache">pour <?= e($d['prenom']) ?></span></label>
          <input class="champ__zone" type="text" inputmode="numeric" maxlength="3" autocomplete="off" id="y-<?= e($id) ?>" name="y_<?= e($id) ?>" value="<?= e($y) ?>"<?= isset($erreurs[$id]) ? ' aria-invalid="true" aria-describedby="erreur-' . e($id) . '"' : '' ?>>
        </div>
      </div>
      <div class="admin__fleches">
<?php foreach ($fleches as $direction => [$fleche, $sens]): ?>
        <button class="scene__bouton" type="submit" name="fleche" value="<?= e($id . ':' . $direction) ?>" aria-label="Déplacer <?= e($d['prenom']) ?> <?= e($sens) ?>"><span aria-hidden="true"><?= e($fleche) ?></span></button>
<?php endforeach; ?>
      </div>
    </fieldset>
<?php endforeach; ?>
    <div class="admin__actions">
      <button class="bouton bouton--plein bouton--56" type="submit" name="action" value="enregistrer">Enregistrer les placements</button>
<?php if ($courante > 0): ?>
      <button class="bouton bouton--contour" type="submit" name="action" value="copier">Copier la section précédente <span class="admin__precision">(remplace les placements de celle-ci)</span></button>
<?php endif; ?>
    </div>
  </form>
<?php endif; ?>
</div>
