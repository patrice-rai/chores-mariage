<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Grille « Qui maîtrise quoi » (maquette 1e) : vraie table (caption, th scope), glyphes ✓ ≈ · doublés d'un mot
 * pour les lecteurs d'écran, légende visible ; la table défile dans sa propre zone (role region, tabindex 0).
 * @var string $id
 * @var string $legende texte du caption (« Qui maîtrise quoi — Démo »)
 * @var list<string> $sections noms des sections
 * @var list<array{prenom: string, attente: bool, niveaux: list<int>}> $lignes
 * @var string $moi
 */
$glyphes = ['·', '≈', '✓'];
$mots = ['pas encore', 'presque', 'je maîtrise'];
?>
<div class="grille-maitrise">
  <p class="grille-maitrise__legende"><span aria-hidden="true">✓</span> je maîtrise · <span aria-hidden="true">≈</span> presque · <span aria-hidden="true">·</span> pas encore</p>
  <div class="grille-maitrise__defilement tableau-defilant" role="region" aria-labelledby="<?= e($id) ?>" tabindex="0">
    <table class="grille-maitrise__table">
      <caption id="<?= e($id) ?>"><?= e($legende) ?></caption>
      <thead>
        <tr>
          <th scope="col">Cousin</th>
<?php foreach ($sections as $nom): ?>
          <th scope="col"><?= e($nom) ?></th>
<?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
<?php foreach ($lignes as $ligne): ?>
        <tr<?= $ligne['prenom'] === $moi ? ' class="grille-maitrise__moi"' : '' ?>>
          <th scope="row"><?= e($ligne['prenom']) ?><?= $ligne['prenom'] === $moi ? ' (toi)' : '' ?><?= $ligne['attente'] ? ' (en attente)' : '' ?></th>
<?php foreach ($sections as $i => $nom): ?>
<?php $n = $ligne['niveaux'][$i] ?? 0; ?>
          <td class="grille-maitrise__case grille-maitrise__case--<?= e($n) ?>"><span aria-hidden="true"><?= e($glyphes[$n]) ?></span><span class="visuellement-cache"><?= e($mots[$n]) ?></span></td>
<?php endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
