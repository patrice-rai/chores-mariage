<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Installation sans SSH (non dessinée : mêmes composants que l'administration). Avant : un champ pour la clé
 * d'administration. Après : le premier lien, montré une seule fois.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var string $titre
 * @var ?string $lien
 * @var ?array<string, int> $compte ajouts de l'import des faits du groupe
 * @var bool $faitsAbsents dossier donnees/ introuvable
 * @var array<string, string> $erreurs « installation-cle » (au champ) ou « installation-base » (générale)
 */
?>
<div class="contenu">
  <h1 class="titre-ecran"><?= e($titre) ?></h1>
<?php if ($lien === null): ?>
  <p>Première mise en route des Chorés du mariage. Avec la clé d’administration, le site prépare sa base, importe les faits du groupe et crée le premier lien secret. Cette page disparaît ensuite.</p>
<?php if ($faitsAbsents): ?>
  <p class="avertissement">Les faits du groupe (dossier <code>donnees/</code>) sont introuvables : le site démarrera sans chansons ni répétitions. Tu pourras les importer plus tard avec <code>php scripts/importer-etat.php</code>.</p>
<?php endif; ?>
  <form class="formulaire" method="post" action="<?= e(url($config, '/installer')) ?>" novalidate>
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <?= composant($c, 'resume-erreurs', ['erreurs' => $erreurs, 'titre' => 'L’installation n’a pas eu lieu :']) ?>
<?php if (isset($erreurs['installation-base'])): ?>
    <p class="avertissement" id="installation-base"><span class="champ__erreur-mot">Erreur :</span> <?= e($erreurs['installation-base']) ?></p>
<?php endif; ?>
    <?= composant($c, 'champ', [
        'type' => 'secret',
        'nom' => 'cle',
        'id' => 'installation-cle',
        'libelle' => 'Clé d’administration',
        'aide' => 'La valeur de CLE_ADMIN dans le fichier de configuration (43 caractères), collée sans les guillemets.',
        'erreur' => $erreurs['installation-cle'] ?? null,
        'requis' => true,
        'attributs' => ['autocomplete' => 'off'],
    ]) ?>
    <p data-avec-js hidden><button class="bouton bouton--contour" type="button" data-afficher-secret aria-controls="installation-cle">Afficher la clé</button></p>
    <p><button class="bouton bouton--plein" type="submit">Installer le site</button></p>
  </form>
<?php else: ?>
  <p class="confirmation" role="status">C’est prêt : la base est en place et le premier lien est créé.</p>
<?php if ($compte !== null): ?>
  <p>Faits du groupe importés : <?= e($compte['chansons']) ?> chanson(s), <?= e($compte['liens']) ?> lien(s), <?= e($compte['repetitions']) ?> répétition(s), <?= e($compte['presences']) ?> réponse(s), <?= e($compte['checklist']) ?> ligne(s) de check-list.</p>
<?php else: ?>
  <p class="avertissement">Les faits du groupe n’ont pas été importés (dossier <code>donnees/</code> introuvable).</p>
<?php endif; ?>
  <p>Ce lien n’est montré qu’ici, une seule fois : copie-le maintenant. Pour en avoir un autre plus tard, passe par l’administration (« Nouveau lien »).</p>
  <div class="champ">
    <label class="champ__etiquette" for="premier-lien">Le lien (à copier, non modifiable)</label>
    <textarea class="champ__zone champ__zone--lecture" id="premier-lien" rows="3" readonly><?= e($lien) ?></textarea>
  </div>
  <p data-avec-js hidden><button class="bouton bouton--contour" type="button" data-copier="premier-lien" data-copier-fait="Lien copié." data-copier-statut="copie-statut">Copier le lien</button></p>
  <p class="champ__aide" id="copie-statut" role="status"></p>
  <p>Cet appareil a déjà l’accès et l’administration. Touche le lien pour choisir ton prénom, puis ouvre l’administration.</p>
  <p><a class="bouton bouton--plein" href="<?= e($lien) ?>">Entrer avec le lien</a></p>
  <p><a class="bouton bouton--contour" href="<?= e(url($config, '/admin')) ?>">Ouvrir l’administration</a></p>
<?php endif; ?>
</div>
