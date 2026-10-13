<?php

declare(strict_types=1);

/**
 * Lien manquant ou périmé (absent de la maquette : même bandeau que l'entrée, aucun formulaire).
 */
?>
<div class="bandeau">
  <h1 class="bandeau__titre">Chorés du mariage</h1>
  <?php if ($perime ?? false): ?>
  <p class="bandeau__texte">Ce lien n’est plus valable : Patrice en a créé un nouveau. Demande-le-lui.</p>
  <?php else: ?>
  <p class="bandeau__texte">Le site s’ouvre avec le lien secret envoyé par Patrice : touche-le une fois sur cet appareil, il te reconnaîtra ensuite. Pas de lien ? Demande-le à Patrice.</p>
  <?php endif; ?>
</div>
<?php if ($oublier ?? false): ?>
<p class="visuellement-cache" data-oublier-telephone>Ce téléphone oublie ce qu’il gardait du site.</p>
<?php endif; ?>
