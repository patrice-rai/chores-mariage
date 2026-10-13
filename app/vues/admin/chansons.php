<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\Controleurs\STATUTS_CHANSON;

/**
 * Liste des chansons (administration) : statut, danseurs, sections, vidéo de référence ; ajout d'une chanson.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{chanson: array<string, mixed>, danseurs: int, sections: int}> $lignes
 * @var array<string, string> $erreurs
 * @var string $titreSaisi
 */
?>
<div class="contenu">
  <h1 class="titre-ecran">Chansons</h1>
<?php if ($lignes === []): ?>
  <p>Aucune chanson : lance l’import des faits du groupe (docs/ADMIN.md) ou ajoute-en une ci-dessous.</p>
<?php else: ?>
  <div class="tableau-defilant" role="region" aria-labelledby="liste-chansons" tabindex="0">
    <table class="tableau">
      <caption id="liste-chansons">Les chansons, par ordre de passage puis par titre</caption>
      <thead><tr><th scope="col">Chanson</th><th scope="col">Statut</th><th scope="col">Danseurs</th><th scope="col">Sections</th><th scope="col">Vidéo de référence</th></tr></thead>
      <tbody>
<?php foreach ($lignes as $l): ?>
        <tr>
          <th scope="row"><a class="admin__lien" href="<?= e(url($config, '/admin/chansons/' . $l['chanson']['slug'])) ?>"><?= e($l['chanson']['titre']) ?></a></th>
          <td><?= e(STATUTS_CHANSON[$l['chanson']['statut']] ?? $l['chanson']['statut']) ?></td>
          <td><?= e($l['danseurs']) ?></td>
          <td><?= $l['sections'] > 0 ? e($l['sections']) : 'pas encore' ?></td>
          <td><?= $l['chanson']['video_ref_id'] !== null ? 'déposée' : 'pas encore' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

  <section class="bloc" aria-labelledby="ajouter-chanson">
    <h2 id="ajouter-chanson">Ajouter une chanson</h2>
    <form class="formulaire" method="post" action="<?= e(url($config, '/admin/chansons')) ?>" novalidate>
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <?= composant($c, 'resume-erreurs', ['erreurs' => isset($erreurs['titre']) ? ['nouvelle-chanson' => $erreurs['titre']] : []]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'titre', 'id' => 'nouvelle-chanson', 'libelle' => 'Titre', 'requis' => true, 'valeur' => $titreSaisi, 'erreur' => $erreurs['titre'] ?? null, 'aide' => 'Elle arrive « proposée », sans danseur : complète sa fiche ensuite.', 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off']]) ?>
      <button class="bouton bouton--plein" type="submit">Ajouter</button>
    </form>
  </section>
</div>
