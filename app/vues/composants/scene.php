<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;
use function Chores\pas_de_cinq;
use function Chores\position_en_mots;
use function Chores\prenom_abrege;

use const Chores\CHAMP_CSRF;

/**
 * Scène de placements (maquette 1m, 1u ; spec § 9) : vue de dessus, public en bas. Sans attribut style : la
 * scène est une grille et chaque point reçoit les classes col-N lig-N (pas de 5 %). Les flèches (SVG aria-hidden)
 * montrent le déplacement vers la section suivante ; la liste textuelle dit tout en mots (lecteurs d'écran, zoom).
 * Sections : un formulaire GET (select + Afficher) encadré des liens ← → ; « Me surligner » ; ↑ ↓ ← → déplacent mon point (POST).
 * @var Chores\Contexte $c
 * @var string $id
 * @var list<string> $sections noms des sections
 * @var int $courante index de la section affichée
 * @var list<array{prenom: string, x: int, y: int}> $points
 * @var ?list<array{prenom: string, x: int, y: int}> $suivants positions à la section suivante (null : dernière)
 * @var string $comptes « 2 × 8 · 0:00 → 0:08 »
 * @var string $moi prénom choisi
 * @var bool $surligner
 * @var bool $grand points de 68 px (mode scène)
 * @var string $hrefPage adresse de la page sans paramètres
 * @var ?string $actionDeplacer adresse du formulaire de déplacement, null si les placements sont verrouillés
 * @var list<string> $nonPlaces prénoms de la choré sans point sur cette section (« non placé »)
 * @var bool $jeDanse le cousin fait partie de la choré
 * @var string $suite paramètres ajoutés aux adresses de la page (« &scene=1 »)
 * @var string $ancre identifiant visé après navigation (par défaut $id)
 * @var int $niveau niveau du titre de section
 */
$suivants ??= null;
$grand ??= false;
$actionDeplacer ??= null;
$niveau ??= 2;
$nonPlaces ??= [];
$jeDanse ??= true;
$suite ??= '';
$ancre ??= $id;
$nombre = count($sections);
$nomCourante = $sections[$courante] ?? '';
$nomSuivante = $sections[$courante + 1] ?? null;
$parPrenom = [];
foreach ($suivants ?? [] as $p) {
    $parPrenom[$p['prenom']] = $p;
}
$lien = static fn (int $i, bool $s): string => $hrefPage . '?section=' . ($i + 1) . ($s ? '' : '&surligner=0') . $suite . '#' . $ancre;
$caches = (str_contains($suite, 'scene=1') ? '<input type="hidden" name="scene" value="1">' : '')
    . (str_contains($suite, 'depuis=jour-j') ? '<input type="hidden" name="depuis" value="jour-j">' : '');
$jeSuisPlace = in_array($moi, array_column($points, 'prenom'), true);
?>
<section class="scene<?= $grand ? ' scene--grand' : '' ?>" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-titre">
  <form class="scene__navigation" method="get" action="<?= e($hrefPage) ?>#<?= e($ancre) ?>">
    <?= $caches ?>
    <label class="scene__etiquette" for="<?= e($id) ?>-section">Section</label>
    <select class="champ__zone scene__select" id="<?= e($id) ?>-section" name="section">
<?php foreach ($sections as $i => $nom): ?>
      <option value="<?= e($i + 1) ?>"<?= $i === $courante ? ' selected' : '' ?>><?= e(($i + 1) . '. ' . $nom) ?></option>
<?php endforeach; ?>
    </select>
<?php if (!$surligner): ?>
    <input type="hidden" name="surligner" value="0">
<?php endif; ?>
<?php if ($courante > 0): ?>
    <a class="scene__fleche-section scene__fleche-section--avant" href="<?= e($lien($courante - 1, $surligner)) ?>"><span aria-hidden="true">←</span><span class="visuellement-cache">Section précédente</span></a>
<?php else: ?>
    <span class="scene__fleche-section scene__fleche-section--avant scene__fleche-section--inactive"><span aria-hidden="true">←</span><span class="visuellement-cache">Pas de section avant celle-ci</span></span>
<?php endif; ?>
    <button class="bouton bouton--secondaire scene__afficher" type="submit">Afficher<span class="visuellement-cache"> cette section</span></button>
<?php if ($courante < $nombre - 1): ?>
    <a class="scene__fleche-section scene__fleche-section--apres" href="<?= e($lien($courante + 1, $surligner)) ?>"><span aria-hidden="true">→</span><span class="visuellement-cache">Section suivante</span></a>
<?php else: ?>
    <span class="scene__fleche-section scene__fleche-section--apres scene__fleche-section--inactive"><span aria-hidden="true">→</span><span class="visuellement-cache">Pas de section après celle-ci</span></span>
<?php endif; ?>
  </form>
  <div class="scene__tete">
    <h<?= (int) $niveau ?> class="scene__titre" id="<?= e($id) ?>-titre"><?= e(($courante + 1) . '. ' . $nomCourante) ?></h<?= (int) $niveau ?>>
    <p class="scene__comptes"><?= e($comptes) ?></p>
  </div>
  <div class="scene__plateau" role="img" aria-label="Scène vue de dessus, public en bas : les positions sont écrites dans la liste qui suit.">
    <svg class="scene__dessin" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
      <defs>
        <marker id="<?= e($id) ?>-pointe" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0,0 L6,3 L0,6 z" fill="currentColor"></path></marker>
      </defs>
<?php foreach ([20, 40, 60, 80] as $t): ?>
      <line class="scene__quadrillage" x1="<?= $t ?>" y1="0" x2="<?= $t ?>" y2="100" vector-effect="non-scaling-stroke"></line>
      <line class="scene__quadrillage" x1="0" y1="<?= $t ?>" x2="100" y2="<?= $t ?>" vector-effect="non-scaling-stroke"></line>
<?php endforeach; ?>
<?php foreach ($points as $p): ?>
<?php $s = $parPrenom[$p['prenom']] ?? null; ?>
<?php if ($s !== null && ($s['x'] !== $p['x'] || $s['y'] !== $p['y'])): ?>
      <line class="scene__deplacement<?= $surligner && $p['prenom'] === $moi ? ' scene__deplacement--moi' : '' ?>" x1="<?= e($p['x']) ?>" y1="<?= e($p['y']) ?>" x2="<?= e($s['x']) ?>" y2="<?= e($s['y']) ?>" stroke-dasharray="2 2" marker-end="url(#<?= e($id) ?>-pointe)" vector-effect="non-scaling-stroke"></line>
<?php endif; ?>
<?php endforeach; ?>
    </svg>
<?php foreach ($points as $p): ?>
    <span class="scene__ancre col-<?= e(pas_de_cinq($p['x'])) ?> lig-<?= e(pas_de_cinq($p['y'])) ?>"><span class="scene__point<?= $surligner && $p['prenom'] === $moi ? ' scene__point--moi' : '' ?>" title="<?= e($p['prenom']) ?>"><?= e(prenom_abrege($p['prenom'])) ?></span></span>
<?php endforeach; ?>
    <p class="scene__public" aria-hidden="true">Public</p>
  </div>
  <p class="scene__aide"><?= $nomSuivante !== null ? 'Les flèches montrent où chacun va à la section suivante (' . e($nomSuivante) . ').' : 'Dernière section : on tient la pose.' ?></p>
  <h<?= (int) $niveau + 1 ?> class="scene__liste-titre">Les positions en mots</h<?= (int) $niveau + 1 ?>>
  <ul class="scene__liste">
<?php foreach ($points as $p): ?>
<?php $s = $parPrenom[$p['prenom']] ?? null; ?>
    <li<?= $p['prenom'] === $moi ? ' class="scene__liste-moi"' : '' ?>><strong><?= e($p['prenom']) ?><?= $p['prenom'] === $moi ? ' (toi)' : '' ?> :</strong> <?= e(position_en_mots($p['x'], $p['y'])) ?><?php if ($s !== null && $nomSuivante !== null): ?> ; ensuite (<?= e($nomSuivante) ?>) : <?= e(($s['x'] === $p['x'] && $s['y'] === $p['y']) ? 'ne bouge pas' : position_en_mots($s['x'], $s['y'])) ?><?php endif; ?>.</li>
<?php endforeach; ?>
  </ul>
<?php if ($points === []): ?>
  <p class="scene__aide">Personne n’est encore placé sur cette section : Patrice pose les placements.</p>
<?php endif; ?>
<?php if ($nonPlaces !== [] && $points !== []): ?>
  <p class="scene__non-places"><strong>Non placé<?= count($nonPlaces) > 1 ? 's' : '' ?> :</strong> <?= e(implode(', ', $nonPlaces)) ?>.</p>
<?php endif; ?>
  <form class="scene__options" method="get" action="<?= e($hrefPage) ?>#<?= e($ancre) ?>">
    <?= $caches ?>
    <input type="hidden" name="section" value="<?= e($courante + 1) ?>">
    <button class="bouton bouton--choix scene__surligner" type="submit" name="surligner" value="<?= $surligner ? '0' : '1' ?>" aria-pressed="<?= $surligner ? 'true' : 'false' ?>">Me surligner : <?= $surligner ? 'oui' : 'non' ?></button>
  </form>
<?php if ($actionDeplacer !== null && $jeSuisPlace): ?>
  <form class="scene__deplacer" method="post" action="<?= e($actionDeplacer) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <?= $caches ?>
    <input type="hidden" name="section" value="<?= e($courante + 1) ?>">
    <fieldset class="scene__groupe">
      <legend class="scene__legende">Déplacer mon point (<?= e($moi) ?>)</legend>
      <button class="scene__bouton" type="submit" name="direction" value="gauche" aria-label="Me déplacer à gauche"><span aria-hidden="true">←</span></button>
      <button class="scene__bouton" type="submit" name="direction" value="droite" aria-label="Me déplacer à droite"><span aria-hidden="true">→</span></button>
      <button class="scene__bouton" type="submit" name="direction" value="fond" aria-label="Me déplacer vers le fond"><span aria-hidden="true">↑</span></button>
      <button class="scene__bouton" type="submit" name="direction" value="public" aria-label="Me déplacer vers le public"><span aria-hidden="true">↓</span></button>
    </fieldset>
  </form>
<?php elseif ($actionDeplacer !== null && $jeDanse): ?>
  <p class="scene__aide">Ta place sur cette section n’est pas encore posée : Patrice s’en charge.</p>
<?php elseif ($actionDeplacer === null): ?>
  <p class="scene__aide">Les placements sont fixés : demande à Patrice pour un changement.</p>
<?php endif; ?>
</section>
