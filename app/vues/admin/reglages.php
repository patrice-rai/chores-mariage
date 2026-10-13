<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Réglages (non dessiné) : date et lieu du mariage, durée accordée, sondage, texte d'entrée, contact, verrou.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, string|bool> $valeurs
 * @var array<string, string> $erreurs
 * @var ?string $confirmation
 */
$champs = [
    'date_mariage' => ['Date du mariage', 'Écris-la AAAA-MM-JJ. Laisse vide tant qu’elle n’est pas connue : le site écrit « [date] ».', ['maxlength' => '10', 'inputmode' => 'numeric']],
    'lieu_mariage' => ['Lieu du mariage', 'Vide : le site écrit « [lieu] ».', ['maxlength' => '120']],
    'duree_danses_min' => ['Durée accordée aux danses (minutes)', 'Vide tant qu’elle n’est pas connue.', ['inputmode' => 'numeric', 'maxlength' => '3']],
    'lien_sondage' => ['Lien du sondage de dates', 'Une adresse https:// (Framadate, par exemple). Vide : « Pas de sondage en cours : dis tes dispos à Patrice ».', ['maxlength' => '300', 'inputmode' => 'url']],
    'texte_entree' => ['Texte d’accueil de l’entrée', 'Vide : « Cinq chorés, neuf cousins, un seul lien. Touche ton prénom et on y va. »', ['maxlength' => '200']],
    'contact_patrice' => ['Comment te joindre (pied de page)', 'Par exemple « sur WhatsApp ». Attention : ce texte se lit sur toutes les pages, y compris celle qu’on voit sans le lien. Vide : « [contact] ».', ['maxlength' => '80']],
];
$resume = [];
foreach ($erreurs as $nom => $message) {
    $resume['reglage-' . $nom] = ($champs[$nom][0] ?? $nom) . ' : ' . $message;
}
?>
<div class="contenu">
  <h1 class="titre-ecran">Réglages</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <form class="formulaire" method="post" action="<?= e(url($config, '/admin/reglages')) ?>" novalidate>
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <?= composant($c, 'resume-erreurs', ['erreurs' => $resume]) ?>
<?php foreach ($champs as $nom => [$libelle, $aide, $attributs]): ?>
    <?= composant($c, 'champ', [
        'type' => 'texte',
        'nom' => $nom,
        'id' => 'reglage-' . $nom,
        'libelle' => $libelle,
        'aide' => $aide,
        'erreur' => $erreurs[$nom] ?? null,
        'valeur' => (string) $valeurs[$nom],
        'attributs' => $attributs + ['autocomplete' => 'off'],
    ]) ?>
<?php endforeach; ?>
    <?= composant($c, 'champ', [
        'type' => 'case',
        'nom' => 'placements_verrouilles',
        'id' => 'reglage-placements_verrouilles',
        'libelle' => 'Placements fixés : les cousins ne déplacent plus leur point',
        'coche' => (bool) $valeurs['placements_verrouilles'],
    ]) ?>
    <button class="bouton bouton--plein bouton--56" type="submit">Enregistrer les réglages</button>
  </form>
</div>
