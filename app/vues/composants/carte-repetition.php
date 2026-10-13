<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\e;
use function Chores\lien_sortant_valide;

/**
 * Carte « Prochaine répétition » (maquette 1b) : aplat grenat, date, lieu, ce qu'on travaille, réponses, présence.
 * Variantes : réponses (qui vient, qui ne peut pas), personne n'a répondu, date pas encore fixée (piste du sondage,
 * lien du sondage ou encart de repli « Pas de sondage en cours »), avec ou sans boutons de présence.
 * @var Chores\Contexte $c
 * @var ?string $quand date et heure en toutes lettres, null si pas encore fixée
 * @var ?string $lieu
 * @var ?string $travail ce qu'on travaille
 * @var ?string $piste date pas fixée : l'option en tête du sondage (« samedi 17 octobre, 2 dispos »)
 * @var list<string> $viennent
 * @var list<string> $nePeuventPas
 * @var ?string $maReponse
 * @var ?string $action adresse du formulaire de présence (null : pas de boutons)
 * @var ?string $sujet ce dont parlent les boutons (« la répétition du samedi 17 octobre »)
 * @var ?string $retour « moi » : la réponse ramène sur Ma page
 * @var ?string $hrefRepetitions lien « Voir les répétitions »
 * @var ?string $lienSondage lien externe (réglages), null si aucun
 * @var ?string $repli texte quand il n'y a pas de lien de sondage (par défaut « Pas de sondage en cours… »)
 * @var string $id identifiant unique du titre
 * @var int $niveau niveau du titre (2 par défaut, sur Ma page)
 * @var ?string $precision précision lue après le titre (gabarits : plusieurs cartes sur la même page)
 */
$precision ??= null;
$niveau ??= 2;
$quand ??= null;
$lieu ??= null;
$travail ??= null;
$piste ??= null;
$viennent ??= [];
$nePeuventPas ??= [];
$maReponse ??= null;
$action ??= null;
$sujet ??= $quand !== null ? 'la répétition du ' . $quand : 'cette date proposée';
$retour ??= null;
$hrefRepetitions ??= null;
$repli ??= 'Pas de sondage en cours : dis tes dispos à Patrice.';
$lienSondage = lien_sortant_valide($lienSondage ?? null) ? $lienSondage : null;
?>
<section class="carte-repetition" aria-labelledby="<?= e($id) ?>">
  <h<?= (int) $niveau ?> class="carte-repetition__etiquette" id="<?= e($id) ?>">Prochaine répétition<?= $quand === null ? ' — date pas encore fixée' : '' ?><?php if ($precision !== null): ?><span class="visuellement-cache"> (<?= e($precision) ?>)</span><?php endif; ?></h<?= (int) $niveau ?>>
<?php if ($quand !== null): ?>
  <p class="carte-repetition__quand"><?= e($quand) ?></p>
<?php endif; ?>
<?php if ($lieu !== null): ?>
  <p class="carte-repetition__lieu"><?= e($lieu) ?></p>
<?php endif; ?>
<?php if ($quand === null): ?>
<?php if ($piste !== null): ?>
  <p class="carte-repetition__lieu"><?= e($piste) ?></p>
<?php endif; ?>
<?php if ($lienSondage !== null): ?>
  <a class="carte-repetition__sondage" href="<?= e($lienSondage) ?>" rel="noopener noreferrer">Proposer des dates <span aria-hidden="true">↗</span><span class="visuellement-cache">(sondage, nouveau site)</span></a>
<?php else: ?>
  <p class="carte-repetition__repli"><?= e($repli) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($travail !== null): ?>
  <p class="carte-repetition__travail">On travaille : <?= e($travail) ?></p>
<?php endif; ?>
<?php if ($action !== null): ?>
<?php if ($quand === null && $piste !== null): ?>
  <p class="carte-repetition__question">Tu es dispo ce jour-là ?</p>
<?php endif; ?>
  <?= composant($c, 'presence', ['action' => $action, 'reponse' => $maReponse, 'sujet' => $sujet, 'variante' => 'grenat', 'retour' => $retour]) ?>
<?php endif; ?>
<?php if ($quand !== null || $action !== null): ?>
  <p class="carte-repetition__reponses">
<?php if ($viennent === [] && $nePeuventPas === []): ?>
    Personne n’a encore répondu. Sois la première ou le premier.
<?php else: ?>
    Viennent : <?= e($viennent === [] ? 'personne pour l’instant' : implode(', ', $viennent)) ?><?php if ($nePeuventPas !== []): ?> · Ne peuvent pas : <?= e(implode(', ', $nePeuventPas)) ?><?php endif; ?>.
<?php endif; ?>
  </p>
<?php endif; ?>
<?php if ($hrefRepetitions !== null): ?>
  <a class="carte-repetition__sondage" href="<?= e($hrefRepetitions) ?>">Voir les répétitions</a>
<?php endif; ?>
</section>
