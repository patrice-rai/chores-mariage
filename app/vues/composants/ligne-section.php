<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;
use function Chores\format_comptes;
use function Chores\format_duree_section;

use const Chores\CHAMP_CSRF;

/**
 * Ligne de section (maquette 1e, planche 1v) : numéro, nom, « n × 8 · 0:17 », trois niveaux, section courante.
 * Toucher la ligne choisit la section : lien ?section=N sans JavaScript, intercepté par lecteur.js (data-section).
 * Le niveau choisi est dit par aria-pressed et écrit (« ✓ ») ; « Je maîtrise » est le seul état en or.
 * Sans $actionNiveau (cousin qui ne danse pas sur la choré), pas de formulaire de niveau.
 * @var Chores\Contexte $c
 * @var int $index à partir de 0
 * @var array{nom: string, debut: float, fin: float, comptes: int, id?: int} $section
 * @var bool $courante
 * @var int $niveau 0, 1 ou 2
 * @var string $hrefChoisir adresse de la page avec ?section=N
 * @var ?string $actionNiveau adresse du formulaire de niveau
 */
$actionNiveau ??= null;
$niveaux = ['Pas encore', 'Presque', 'Je maîtrise'];
$numero = $index + 1;
$nomGroupe = 'Ton niveau pour ' . $section['nom'];
$ancre = isset($section['id']) ? ' id="section-' . e($section['id']) . '"' : '';
?>
<li class="ligne-section<?= $courante ? ' ligne-section--courante' : '' ?>" data-ligne-section="<?= e($index) ?>"<?= $ancre ?>>
  <a class="ligne-section__choisir" href="<?= e($hrefChoisir) ?>" data-section="<?= e($index) ?>"<?= $courante ? ' aria-current="true"' : '' ?>>
    <span class="ligne-section__numero" aria-hidden="true"><?= e($numero) ?></span>
    <span class="ligne-section__nom"><span class="visuellement-cache">Section <?= e($numero) ?> : </span><?= e($section['nom']) ?></span>
    <span class="ligne-section__comptes"><?= e(format_comptes($section['comptes'])) ?> · <?= e(format_duree_section($section['debut'], $section['fin'])) ?></span>
    <span class="ligne-section__en-cours" data-en-cours<?= $courante ? '' : ' hidden' ?>>en cours</span>
  </a>
<?php if ($actionNiveau !== null): ?>
  <form class="ligne-section__niveaux" method="post" action="<?= e($actionNiveau) ?>" data-niveau="<?= e($index) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <fieldset class="ligne-section__groupe">
      <legend class="visuellement-cache"><?= e($nomGroupe) ?></legend>
<?php foreach ($niveaux as $valeur => $libelle): ?>
      <button class="niveau niveau--<?= e($valeur) ?>" type="submit" name="niveau" value="<?= e($valeur) ?>" aria-pressed="<?= $niveau === $valeur ? 'true' : 'false' ?>"><?= e($libelle) ?><span class="niveau__coche" aria-hidden="true"<?= $niveau === $valeur ? '' : ' hidden' ?>> ✓</span></button>
<?php endforeach; ?>
    </fieldset>
  </form>
<?php endif; ?>
</li>
