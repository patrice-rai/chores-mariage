<?php

declare(strict_types=1);

use function Chores\date_paris;
use function Chores\e;

use const Chores\TYPES_JOURNAL;

/**
 * Journal (administration) : les 200 dernières lignes, sans IP ni appareil.
 * @var list<array{quand: string, type: string, prenom: ?string, detail: ?string}> $journal
 */
?>
<div class="contenu">
  <h1 class="titre-ecran">Journal</h1>
<?php if ($journal === []): ?>
  <p>Le journal est vide.</p>
<?php else: ?>
  <p class="legende" id="legende-journal-complet">Les <?= e(count($journal)) ?> dernières lignes, de la plus récente à la plus ancienne</p>
  <div class="tableau-defilant" role="region" aria-labelledby="legende-journal-complet" tabindex="0">
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
<?php endif; ?>
</div>
