<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Entrée (planche 1a) : neuf prénoms en boutons de 72 px.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{id: int, prenom: string}> $cousins
 * @var ?string $texteEntree texte d'accueil des réglages (sinon celui de la maquette)
 */
?>
<div class="bandeau">
  <h1 class="bandeau__titre">Chorés du mariage</h1>
  <p class="bandeau__texte"><?= e(isset($texteEntree) && $texteEntree !== '' ? $texteEntree : 'Cinq chorés, neuf cousins, un seul lien. Touche ton prénom et on y va.') ?></p>
</div>
<form class="entree" method="post" action="<?= e(url($config, '/prenom')) ?>">
  <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
  <fieldset class="entree__groupe">
    <legend class="visuellement-cache">Ton prénom</legend>
    <div class="entree__prenoms">
<?php foreach ($cousins as $cousin): ?>
      <?= composant($c, 'bouton-prenom', ['id' => $cousin['id'], 'prenom' => $cousin['prenom']]) ?>
<?php endforeach; ?>
    </div>
  </fieldset>
</form>
<p class="aide">Tu n’es pas dans la liste ? Demande le lien à Patrice.</p>
