<?php

declare(strict_types=1);

use function Chores\date_paris;
use function Chores\date_sauvegarde;
use function Chores\e;
use function Chores\format_octets;
use function Chores\url;

use const Chores\TYPES_JOURNAL;
use const Chores\VERSION;

/**
 * Tableau de bord de Patrice (non dessiné : mêmes composants, sobres) : accès aux écrans, lien secret, espace,
 * questions ouvertes du groupe (pense-bête, jamais montré aux cousins), entrées, dernières actions.
 * @var array<string, mixed> $config
 * @var int $version
 * @var ?string $creeLe
 * @var int $entrees
 * @var list<array{prenom: string, nombre: int}> $parPrenom
 * @var list<array{quand: string, type: string, prenom: ?string, detail: ?string}> $journal
 * @var int $espaceStockage
 * @var int $espaceVideos
 * @var int $espaceMax
 * @var list<string> $questions
 * @var ?string $importLe
 * @var list<string> $enAttente migrations pas encore appliquées
 * @var ?string $derniereSauvegarde nom de la sauvegarde la plus récente
 */
?>
<div class="contenu">
  <h1 class="titre-ecran">Administration</h1>
<?php if ($enAttente !== []): ?>
  <p class="avertissement">La base attend <?= e(count($enAttente)) ?> mise(s) à jour : <a href="<?= e(url($config, '/admin/base')) ?>">Base et sauvegardes</a>.</p>
<?php endif; ?>

  <nav class="admin__navigation" aria-label="Administration">
    <a class="bouton bouton--plein" href="<?= e(url($config, '/admin/chansons')) ?>">Chansons, sections et placements</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/repetitions')) ?>">Répétitions</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/videos')) ?>">Vidéos</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/jour-j')) ?>">Jour J</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/reglages')) ?>">Réglages</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/journal')) ?>">Journal complet</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/base')) ?>">Base et sauvegardes</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/gabarits')) ?>">Voir les gabarits</a>
  </nav>

  <section class="bloc" aria-labelledby="titre-lien">
    <h2 id="titre-lien">Lien secret</h2>
<?php if ($version > 0): ?>
    <p>Lien en service : version <?= e($version) ?>, créé le <?= e(date_paris((string) $creeLe)) ?>.</p>
<?php else: ?>
    <p>Aucun lien n’a encore été créé : personne ne peut entrer.</p>
<?php endif; ?>
    <p>Le lien lui-même n’est gardé nulle part en clair : pour le redonner, crée-en un nouveau.</p>
    <p><a class="bouton bouton--plein" href="<?= e(url($config, '/admin/lien')) ?>">Nouveau lien</a></p>
  </section>

  <section class="bloc" aria-labelledby="titre-espace">
    <h2 id="titre-espace">Espace</h2>
    <p>Dossier de stockage (base, vidéos, sauvegardes) : <?= e(format_octets($espaceStockage)) ?>.</p>
    <p>Vidéos : <?= e(format_octets($espaceVideos)) ?> sur <?= e(format_octets($espaceMax)) ?> permis.</p>
  </section>

  <section class="bloc" aria-labelledby="titre-maintenance">
    <h2 id="titre-maintenance">Base et sauvegardes</h2>
    <p>Version du site : <?= e(VERSION) ?>. Base : <?= $enAttente === [] ? 'à jour' : e(count($enAttente)) . ' mise(s) à jour en attente' ?>.</p>
    <p><?= $derniereSauvegarde === null ? 'Aucune sauvegarde pour l’instant.' : 'Dernière sauvegarde : ' . e(date_sauvegarde($derniereSauvegarde) ?? $derniereSauvegarde) . '.' ?></p>
    <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin/base')) ?>">Base et sauvegardes</a></p>
  </section>

  <section class="bloc" aria-labelledby="titre-questions">
    <h2 id="titre-questions">Questions ouvertes du groupe</h2>
<?php if ($questions === []): ?>
    <p>Aucune question en attente<?= $importLe === null ? ' : les faits du groupe ne sont pas encore importés (docs/ADMIN.md)' : '' ?>.</p>
<?php else: ?>
    <p>Relevées dans le groupe WhatsApp<?= $importLe !== null ? ' (import du ' . e(date_paris($importLe)) . ')' : '' ?>. Pense-bête : les cousins ne les voient pas.</p>
    <ul class="admin__questions">
<?php foreach ($questions as $question): ?>
      <li><?= e($question) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>

  <section class="bloc" aria-labelledby="titre-entrees">
    <h2 id="titre-entrees">Entrées</h2>
    <p>Entrées par le lien depuis le début du journal : <?= e($entrees) ?>.</p>
    <div class="tableau-defilant" role="region" aria-labelledby="titre-prenoms" tabindex="0">
      <table class="tableau">
        <caption id="titre-prenoms">Choix du prénom, par cousin</caption>
        <thead><tr><th scope="col">Prénom</th><th scope="col">Nombre de fois</th></tr></thead>
        <tbody>
<?php foreach ($parPrenom as $ligne): ?>
          <tr><th scope="row"><?= e($ligne['prenom']) ?></th><td><?= e($ligne['nombre']) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="bloc" aria-labelledby="titre-journal">
    <h2 id="titre-journal">Dernières actions</h2>
<?php if ($journal === []): ?>
    <p>Le journal est vide.</p>
<?php else: ?>
    <p class="legende" id="legende-journal">Les <?= e(count($journal)) ?> dernières lignes, de la plus récente à la plus ancienne</p>
    <div class="tableau-defilant" role="region" aria-labelledby="legende-journal" tabindex="0">
      <table class="tableau">
        <caption class="visuellement-cache">Les <?= e(count($journal)) ?> dernières lignes du journal</caption>
        <thead><tr><th scope="col">Quand</th><th scope="col">Quoi</th><th scope="col">Prénom</th><th scope="col">Détail</th></tr></thead>
        <tbody>
<?php foreach ($journal as $ligne): ?>
          <tr>
            <td><?= e(date_paris($ligne['quand'])) ?></td>
            <td><?= e(TYPES_JOURNAL[$ligne['type']] ?? $ligne['type']) ?></td>
            <td><?= e($ligne['prenom'] ?? 'inconnu') ?></td>
            <td><?= e($ligne['detail'] ?? '') ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin/journal')) ?>">Journal complet</a></p>
<?php endif; ?>
  </section>
</div>
