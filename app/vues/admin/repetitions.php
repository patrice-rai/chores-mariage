<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\date_en_mots;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\STATUTS_REPETITION;

/**
 * Répétitions en administration (non dessiné) : liste (la plus lointaine d'abord), ajout.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{repetition: array<string, mixed>, reponses: array{oui: list<string>, non: list<string>, peut_etre: list<string>}}> $lignes
 * @var string $aujourdhui
 * @var array<string, string> $erreurs
 * @var array<string, string> $saisie
 * @var ?string $confirmation
 */
$libelles = ['repetition-date' => 'Date', 'repetition-heure' => 'Heure', 'repetition-lieu' => 'Lieu', 'repetition-statut' => 'Statut', 'repetition-travail' => 'On travaille', 'repetition-note' => 'Note'];
$resume = [];
foreach ($erreurs as $id => $message) {
    $resume[$id] = ($libelles[$id] ?? 'Répétition') . ' : ' . $message;
}
?>
<div class="contenu">
  <h1 class="titre-ecran">Répétitions</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <section class="bloc" aria-labelledby="liste-repetitions">
    <h2 id="liste-repetitions">Toutes les répétitions</h2>
<?php if ($lignes === []): ?>
    <p>Aucune répétition pour l’instant.</p>
<?php endif; ?>
    <ul class="admin__liste">
<?php foreach ($lignes as $ligne): ?>
<?php $r = $ligne['repetition']; $rep = $ligne['reponses']; ?>
      <li class="admin__ligne">
        <a class="admin__lien" href="<?= e(url($config, '/admin/repetitions/' . (int) $r['id'])) ?>"><?= e(ucfirst(date_en_mots((string) $r['date'], ''))) ?></a>
        <span><?= e(STATUTS_REPETITION[$r['statut']] ?? $r['statut']) ?> · <?= e(count($rep['oui'])) ?> oui, <?= e(count($rep['non'])) ?> non, <?= e(count($rep['peut_etre'])) ?> peut-être<?= $r['compte_rendu'] !== null ? ' · compte rendu écrit' : '' ?></span>
      </li>
<?php endforeach; ?>
    </ul>
  </section>

  <section class="bloc" aria-labelledby="nouvelle-repetition">
    <h2 id="nouvelle-repetition">Ajouter une répétition</h2>
    <form class="formulaire" method="post" action="<?= e(url($config, '/admin/repetitions')) ?>" novalidate>
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resume, 'titre' => 'La répétition n’a pas été ajoutée :']) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'date', 'id' => 'repetition-date', 'libelle' => 'Date', 'requis' => true, 'aide' => 'AAAA-MM-JJ.', 'valeur' => $saisie['date'], 'erreur' => $erreurs['repetition-date'] ?? null, 'attributs' => ['maxlength' => '10', 'inputmode' => 'numeric', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'heure', 'id' => 'repetition-heure', 'libelle' => 'Heure', 'aide' => 'Par exemple « 14 h – 18 h ».', 'valeur' => $saisie['heure'], 'erreur' => $erreurs['repetition-heure'] ?? null, 'attributs' => ['maxlength' => '60', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'lieu', 'id' => 'repetition-lieu', 'libelle' => 'Lieu', 'valeur' => $saisie['lieu'], 'erreur' => $erreurs['repetition-lieu'] ?? null, 'attributs' => ['maxlength' => '120', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'statut', 'id' => 'repetition-statut', 'libelle' => 'Statut', 'requis' => true, 'options' => STATUTS_REPETITION, 'valeur' => $saisie['statut'], 'erreur' => $erreurs['repetition-statut'] ?? null]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'travail', 'id' => 'repetition-travail', 'libelle' => 'On travaille', 'valeur' => $saisie['travail'], 'erreur' => $erreurs['repetition-travail'] ?? null, 'attributs' => ['maxlength' => '200', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'note', 'id' => 'repetition-note', 'libelle' => 'Note', 'valeur' => $saisie['note'], 'erreur' => $erreurs['repetition-note'] ?? null, 'attributs' => ['maxlength' => '300', 'autocomplete' => 'off']]) ?>
      <button class="bouton bouton--plein bouton--56" type="submit">Ajouter la répétition</button>
    </form>
  </section>
</div>
