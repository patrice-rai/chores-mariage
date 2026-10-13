<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Nouveau lien secret (non dessiné). Le lien n'est affiché qu'une fois, juste après sa création.
 * @var Chores\Contexte $c
 * @var string $titre
 * @var array<string, mixed> $config
 * @var ?string $lien
 * @var int $version
 * @var list<string> $horsGroupe prénoms hors du groupe WhatsApp
 * @var ?bool $dejaRenouvele formulaire renvoyé après un renouvellement
 */
$dejaRenouvele ??= false;
$texte = $lien === null ? '' : 'Voici le nouveau lien des Chorés du mariage : ' . $lien
    . ' — touche-le une fois, puis choisis ton prénom. Garde-le entre cousins, ne le partage pas ailleurs.';
?>
<div class="contenu">
  <h1 class="titre-ecran"><?= e($titre) ?></h1>
<?php if ($lien === null): ?>
<?php if ($dejaRenouvele): ?>
  <p class="avertissement" role="status">Rien n’a changé : le lien a déjà été renouvelé (version <?= e($version) ?>). Si tu ne l’as pas copié, crées-en un autre.</p>
<?php endif; ?>
  <p>Un nouveau lien remplace l’ancien aussitôt : l’ancien ne marchera plus pour personne, toi compris. Chacun devra toucher le nouveau lien une fois.</p>
  <form method="post" action="<?= e(url($config, '/admin/lien')) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <input type="hidden" name="version" value="<?= e($version) ?>">
    <button class="bouton bouton--plein" type="submit">Créer un nouveau lien</button>
  </form>
  <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin')) ?>">Revenir au tableau</a></p>
<?php else: ?>
  <p class="confirmation" role="status">Nouveau lien créé (version <?= e($version) ?>). L’ancien ne marche plus.</p>
  <p>Ce lien n’est montré qu’ici, une seule fois : copie-le maintenant. Recharger cette page ne le montrera plus et n’en créera pas d’autre.</p>
  <div class="champ">
    <label class="champ__etiquette" for="nouveau-lien">Le lien (à copier, non modifiable)</label>
    <textarea class="champ__zone champ__zone--lecture" id="nouveau-lien" rows="3" readonly><?= e($lien) ?></textarea>
  </div>
  <p data-avec-js hidden><button class="bouton bouton--contour" type="button" data-copier="nouveau-lien" data-copier-fait="Lien copié." data-copier-statut="copie-lien">Copier le lien</button></p>
  <p class="champ__aide" id="copie-lien" role="status"></p>
  <div class="champ">
    <label class="champ__etiquette" for="texte-whatsapp">Texte prêt à coller dans WhatsApp (non modifiable)</label>
    <textarea class="champ__zone champ__zone--lecture" id="texte-whatsapp" rows="5" readonly><?= e($texte) ?></textarea>
  </div>
  <p data-avec-js hidden><button class="bouton bouton--contour" type="button" data-copier="texte-whatsapp" data-copier-fait="Texte copié." data-copier-statut="copie-texte">Copier le texte</button></p>
  <p class="champ__aide" id="copie-texte" role="status"></p>
<?php if ($horsGroupe !== []): ?>
  <p>Pense à l’envoyer aussi à <?= e(implode(' et ', $horsGroupe)) ?>, qui ne sont pas dans le groupe WhatsApp.</p>
<?php endif; ?>
  <p>Ton appareil suit déjà le nouveau lien ; ton prénom est à rechoisir, comme pour tout le monde.</p>
  <p><a class="bouton bouton--plein" href="<?= e($lien) ?>">Entrer avec le nouveau lien</a></p>
<?php endif; ?>
</div>
