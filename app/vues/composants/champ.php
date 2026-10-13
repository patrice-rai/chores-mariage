<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Champ de formulaire (spec § 12) : étiquette visible, aide et erreur reliées par aria-describedby, aria-invalid,
 * valeur conservée (aucune ressaisie). Types : texte, select, fichier, case, zone, secret (jamais réaffiché).
 * @var string $type
 * @var string $nom
 * @var string $id
 * @var string $libelle
 * @var ?string $aide
 * @var ?string $erreur
 * @var ?string $valeur
 * @var array<string, string> $options (select) valeur → libellé
 * @var bool $requis
 * @var bool $sansMention pas de « (facultatif) » sans être obligatoire (liste dont le choix vide est une vraie réponse)
 * @var bool $coche (case)
 * @var array<string, string> $attributs attributs en plus (accept, maxlength, autocomplete…)
 */
$aide ??= null;
$erreur ??= null;
$valeur ??= '';
$options ??= [];
$requis ??= false;
$sansMention ??= false;
$coche ??= false;
$attributs ??= [];
$decrit = array_values(array_filter([$aide !== null ? $id . '-aide' : null, $erreur !== null ? $id . '-erreur' : null]));
$communs = ' id="' . e($id) . '" name="' . e($nom) . '"'
    . ($decrit !== [] ? ' aria-describedby="' . e(implode(' ', $decrit)) . '"' : '')
    . ($erreur !== null ? ' aria-invalid="true"' : '')
    . ($requis ? ' required' : '');
foreach ($attributs as $attribut => $v) {
    if (preg_match('/^[a-z][a-z-]*$/', $attribut) && !str_starts_with($attribut, 'on') && $attribut !== 'style') {
        $communs .= ' ' . $attribut . '="' . e($v) . '"';
    }
}
$mention = $requis || $sansMention ? '' : ' <span class="champ__facultatif">(facultatif)</span>';
?>
<div class="champ champ--<?= e($type) ?><?= $erreur !== null ? ' champ--erreur' : '' ?>">
<?php if ($type === 'case'): ?>
  <div class="champ__ligne-case">
    <input class="champ__case" type="checkbox" value="1"<?= $communs ?><?= $coche ? ' checked' : '' ?>>
    <label class="champ__etiquette champ__etiquette--case" for="<?= e($id) ?>"><?= e($libelle) ?></label>
  </div>
<?php else: ?>
  <label class="champ__etiquette" for="<?= e($id) ?>"><?= e($libelle) ?><?= $mention ?></label>
<?php endif; ?>
<?php if ($aide !== null): ?>
  <p class="champ__aide" id="<?= e($id) ?>-aide"><?= e($aide) ?></p>
<?php endif; ?>
<?php if ($erreur !== null): ?>
  <p class="champ__erreur" id="<?= e($id) ?>-erreur"><span class="champ__erreur-mot">Erreur :</span> <?= e($erreur) ?></p>
<?php endif; ?>
<?php if ($type === 'texte'): ?>
  <input class="champ__zone" type="text" value="<?= e($valeur) ?>"<?= $communs ?>>
<?php elseif ($type === 'secret'): ?>
  <input class="champ__zone" type="password" value=""<?= $communs ?>>
<?php elseif ($type === 'zone'): ?>
  <textarea class="champ__zone champ__zone--texte" rows="4"<?= $communs ?>><?= e($valeur) ?></textarea>
<?php elseif ($type === 'select'): ?>
  <select class="champ__zone"<?= $communs ?>>
<?php foreach ($options as $v => $l): ?>
    <option value="<?= e($v) ?>"<?= (string) $v === $valeur ? ' selected' : '' ?>><?= e($l) ?></option>
<?php endforeach; ?>
  </select>
<?php elseif ($type === 'fichier'): ?>
  <input class="champ__zone champ__zone--fichier" type="file"<?= $communs ?>>
<?php endif; ?>
</div>
