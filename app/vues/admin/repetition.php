<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\date_en_mots;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\Controleurs\CONSTATS;
use const Chores\STATUTS_REPETITION;

/**
 * Une répétition en administration (non dessiné) : date, heure, lieu, statut, « on travaille », note, compte rendu,
 * présents et absents constatés (un menu par cousin, distinct de sa réponse, rappelée à côté).
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $repetition
 * @var list<array{id: int, prenom: string}> $cousins
 * @var array<int, array{reponse: string, source: string}> $reponses
 * @var array<string, mixed> $valeurs
 * @var array<string, string> $erreurs
 * @var ?string $confirmation
 */
$libelles = ['repetition-date' => 'Date', 'repetition-heure' => 'Heure', 'repetition-lieu' => 'Lieu', 'repetition-statut' => 'Statut', 'repetition-travail' => 'On travaille', 'repetition-note' => 'Note', 'repetition-compte_rendu' => 'Compte rendu'];
$resume = [];
foreach ($erreurs as $id => $message) {
    $resume[$id] = (isset($libelles[$id]) ? $libelles[$id] . ' : ' : '') . $message;
}
$motsReponse = ['oui' => 'a dit « je viens »', 'non' => 'a dit « je ne peux pas »', 'peut_etre' => 'dispo d’après le sondage'];
$constats = $valeurs['constats'] ?? [];
?>
<div class="contenu">
  <h1 class="titre-ecran"><?= e(ucfirst(date_en_mots((string) $repetition['date'], ''))) ?></h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <form class="formulaire" method="post" action="<?= e(url($config, '/admin/repetitions/' . (int) $repetition['id'])) ?>" novalidate>
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <?= composant($c, 'resume-erreurs', ['erreurs' => $resume]) ?>
    <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'date', 'id' => 'repetition-date', 'libelle' => 'Date', 'requis' => true, 'aide' => 'AAAA-MM-JJ.', 'valeur' => (string) $valeurs['date'], 'erreur' => $erreurs['repetition-date'] ?? null, 'attributs' => ['maxlength' => '10', 'inputmode' => 'numeric', 'autocomplete' => 'off']]) ?>
    <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'heure', 'id' => 'repetition-heure', 'libelle' => 'Heure', 'valeur' => (string) $valeurs['heure'], 'erreur' => $erreurs['repetition-heure'] ?? null, 'attributs' => ['maxlength' => '60', 'autocomplete' => 'off']]) ?>
    <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'lieu', 'id' => 'repetition-lieu', 'libelle' => 'Lieu', 'valeur' => (string) $valeurs['lieu'], 'erreur' => $erreurs['repetition-lieu'] ?? null, 'attributs' => ['maxlength' => '120', 'autocomplete' => 'off']]) ?>
    <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'statut', 'id' => 'repetition-statut', 'libelle' => 'Statut', 'requis' => true, 'options' => STATUTS_REPETITION, 'valeur' => (string) $valeurs['statut'], 'erreur' => $erreurs['repetition-statut'] ?? null, 'aide' => 'Annulée : les cousins ne peuvent plus répondre. Passée : la carte rejoint les répétitions passées.']) ?>
    <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'travail', 'id' => 'repetition-travail', 'libelle' => 'On travaille', 'valeur' => (string) $valeurs['travail'], 'erreur' => $erreurs['repetition-travail'] ?? null, 'attributs' => ['maxlength' => '200', 'autocomplete' => 'off']]) ?>
    <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'note', 'id' => 'repetition-note', 'libelle' => 'Note', 'valeur' => (string) $valeurs['note'], 'erreur' => $erreurs['repetition-note'] ?? null, 'attributs' => ['maxlength' => '300', 'autocomplete' => 'off']]) ?>
    <?= composant($c, 'champ', ['type' => 'zone', 'nom' => 'compte_rendu', 'id' => 'repetition-compte_rendu', 'libelle' => 'Compte rendu', 'aide' => 'Après coup : ce qui a été travaillé, ce qui reste. 2 000 caractères au plus.', 'valeur' => (string) $valeurs['compte_rendu'], 'erreur' => $erreurs['repetition-compte_rendu'] ?? null, 'attributs' => ['maxlength' => '2000']]) ?>
    <fieldset class="admin__danseurs">
      <legend>Qui était là (constaté)</legend>
      <p class="champ__aide">Distinct des réponses : note ce que tu as vu le jour même.</p>
<?php foreach ($cousins as $cousin): ?>
<?php $id = 'constat-' . $cousin['id']; $rep = $reponses[$cousin['id']] ?? null; ?>
      <?= composant($c, 'champ', [
          'type' => 'select', 'nom' => 'constat[' . $cousin['id'] . ']', 'id' => $id, 'libelle' => $cousin['prenom'], 'sansMention' => true,
          'aide' => $rep !== null ? ucfirst($motsReponse[$rep['reponse']] ?? $rep['reponse']) . ($rep['source'] === 'whatsapp' ? ' (WhatsApp)' : ' (site)') . '.' : 'N’a pas répondu.',
          'options' => CONSTATS, 'valeur' => (string) ($constats[$cousin['id']] ?? ''), 'erreur' => $erreurs[$id] ?? null,
      ]) ?>
<?php endforeach; ?>
    </fieldset>
    <button class="bouton bouton--plein bouton--56" type="submit">Enregistrer la répétition</button>
  </form>
</div>
