<?php

declare(strict_types=1);

/**
 * Lien manquant ou périmé (absent de la maquette : même bandeau que l'entrée, aucun formulaire).
 */
?>
<div class="bandeau">
  <h1 class="bandeau__titre">Chorés du mariage</h1>
  <p class="bandeau__texte">Ce lien n’est plus valable (ou tu n’en as pas). Demande le nouveau à Patrice.</p>
</div>
<?php if ($oublier ?? false): ?>
<p class="visuellement-cache" data-oublier-telephone>Ce téléphone oublie ce qu’il gardait du site.</p>
<?php endif; ?>
