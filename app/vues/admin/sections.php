<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\format_temps;
use function Chores\format_temps_precis;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Sections d'une chanson (administration) : liste ordonnée, chaque section dans son formulaire (nom, début, fin en
 * mm:ss,d, comptes de 8, note), monter, descendre, supprimer (avec confirmation) ; ajout. Avec JavaScript, « Depuis
 * le lecteur » copie la position de la vidéo de référence affichée ici.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $chanson
 * @var list<array{id: int, ordre: int, nom: string, debut: float, fin: float, comptes: int, note: ?string}> $sections
 * @var array<string, array<string, string>> $erreurs par formulaire (« nouvelle » ou id de section)
 * @var array<string, array<string, string>> $saisies valeurs saisies par formulaire
 * @var ?array<string, mixed> $aSupprimer
 * @var ?string $video
 * @var ?string $confirmation
 */
$slug = (string) $chanson['slug'];
$action = url($config, '/admin/chansons/' . $slug . '/sections');
$csrf = '<input type="hidden" name="' . e(CHAMP_CSRF) . '" value="' . e(champ_csrf($c)) . '">';
$nombre = count($sections);
$formulaire = static function (string $cle, array $valeurs, string $prefixe) use ($c, $erreurs, $saisies, $video): string {
    $saisie = $saisies[$cle] ?? null;
    $erreursForm = $erreurs[$cle] ?? [];
    $html = '';
    foreach ([
        'nom' => ['Nom', null, ['maxlength' => '40']],
        'debut' => ['Début', 'Minutes:secondes,dixièmes, par exemple 0:17,5.', ['maxlength' => '10', 'inputmode' => 'decimal']],
        'fin' => ['Fin', null, ['maxlength' => '10', 'inputmode' => 'decimal']],
        'comptes' => ['Comptes de 8', 'Combien de « 8 » dans la section.', ['maxlength' => '2', 'inputmode' => 'numeric']],
        'note' => ['Note', null, ['maxlength' => '200']],
    ] as $nom => [$libelle, $aide, $attributs]) {
        $html .= composant($c, 'champ', [
            'type' => 'texte', 'nom' => $nom, 'id' => $prefixe . '-' . $nom, 'libelle' => $libelle, 'aide' => $aide,
            'requis' => $nom !== 'note', 'erreur' => $erreursForm[$nom] ?? null, 'valeur' => $saisie[$nom] ?? $valeurs[$nom],
            'attributs' => $attributs + ['autocomplete' => 'off'],
        ]);
        if ($video !== null && ($nom === 'debut' || $nom === 'fin')) {
            $html .= '<button class="bouton bouton--secondaire admin__depuis" type="button" data-depuis-lecteur="' . e($prefixe . '-' . $nom) . '" data-js-seulement hidden>'
                . e(($nom === 'debut' ? 'Début' : 'Fin') . ' depuis le lecteur')
                . '<span class="visuellement-cache"> pour ' . e($valeurs['nom'] !== '' ? $valeurs['nom'] : 'la nouvelle section') . '</span></button>';
        }
    }

    return $html;
};
$resume = static function (string $cle, string $prefixe) use ($erreurs): array {
    $liste = [];
    foreach ($erreurs[$cle] ?? [] as $nom => $message) {
        $liste[$prefixe . '-' . $nom] = $message;
    }

    return $liste;
};
?>
<div class="contenu admin">
  <h1 class="titre-ecran">Sections de <?= e($chanson['titre']) ?></h1>
  <nav class="admin__navigation" aria-label="Cette chanson">
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons/' . $slug)) ?>">Fiche de la chanson</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons/' . $slug . '/placements')) ?>">Placements</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/chanson/' . $slug)) ?>">Voir la page des cousins</a>
  </nav>
  <p>Les sections se suivent sans se chevaucher<?= $chanson['duree_s'] !== null ? ' et finissent avant ' . e(format_temps((int) $chanson['duree_s'])) : '' ?>. Supprimer une section efface aussi les niveaux et les placements qui lui sont liés.</p>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if (isset($erreurs['general'])): ?>
  <p class="avertissement" role="alert"><?= e(implode(' ', $erreurs['general'])) ?></p>
<?php endif; ?>
<?php if ($video !== null): ?>
  <section class="bloc" aria-labelledby="lecteur-admin-titre">
    <h2 id="lecteur-admin-titre">Vidéo de référence</h2>
    <video class="admin__video" id="video-admin" src="<?= e($video) ?>" controls playsinline preload="metadata" aria-label="Vidéo de référence : <?= e($chanson['titre']) ?> (musique, sans paroles)"></video>
    <p class="champ__aide">Avec « Depuis le lecteur », mets la vidéo en pause au bon endroit, puis touche le bouton à côté du début ou de la fin.</p>
  </section>
<?php endif; ?>

<?php if ($aSupprimer !== null): ?>
  <section class="bloc admin__confirmation" aria-labelledby="confirmer-suppression">
    <h2 id="confirmer-suppression">Supprimer « <?= e($aSupprimer['nom']) ?> » ?</h2>
    <p>Les niveaux des cousins et les placements de cette section seront effacés aussi. Ça ne se défait pas.</p>
    <form method="post" action="<?= e($action) ?>">
      <?= $csrf ?>
      <input type="hidden" name="id" value="<?= e($aSupprimer['id']) ?>">
      <input type="hidden" name="confirmer" value="1">
      <button class="bouton bouton--plein" type="submit" name="action" value="supprimer">Oui, supprimer la section</button>
    </form>
    <p><a class="bouton bouton--contour" href="<?= e($action . '#section-' . $aSupprimer['id']) ?>">Non, la garder</a></p>
  </section>
<?php endif; ?>

<?php if ($sections === []): ?>
  <p>Aucune section pour l’instant : la page de la chanson dit « Les sections ne sont pas encore posées ».</p>
<?php endif; ?>
  <ol class="admin__sections">
<?php foreach ($sections as $i => $s): ?>
<?php $cle = (string) $s['id']; $prefixe = 'section-' . $s['id']; ?>
    <li class="bloc admin__section" id="<?= e($prefixe) ?>">
      <h2><?= e(($i + 1) . '. ' . $s['nom']) ?></h2>
      <form class="formulaire" method="post" action="<?= e($action) ?>" novalidate>
        <?= $csrf ?>
        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
        <?= composant($c, 'resume-erreurs', ['erreurs' => $resume($cle, $prefixe)]) ?>
        <?= $formulaire($cle, ['nom' => $s['nom'], 'debut' => format_temps_precis($s['debut']), 'fin' => format_temps_precis($s['fin']), 'comptes' => (string) $s['comptes'], 'note' => (string) ($s['note'] ?? '')], $prefixe) ?>
        <div class="admin__actions">
          <button class="bouton bouton--plein" type="submit" name="action" value="enregistrer">Enregistrer <span class="visuellement-cache"><?= e($s['nom']) ?></span></button>
<?php if ($i > 0): ?>
          <button class="bouton bouton--contour" type="submit" name="action" value="monter">Monter <span class="visuellement-cache"><?= e($s['nom']) ?></span></button>
<?php endif; ?>
<?php if ($i < $nombre - 1): ?>
          <button class="bouton bouton--contour" type="submit" name="action" value="descendre">Descendre <span class="visuellement-cache"><?= e($s['nom']) ?></span></button>
<?php endif; ?>
          <a class="bouton bouton--contour" href="<?= e($action . '?supprimer=' . $s['id'] . '#confirmer-suppression') ?>">Supprimer <span class="visuellement-cache"><?= e($s['nom']) ?></span></a>
        </div>
      </form>
    </li>
<?php endforeach; ?>
  </ol>

  <section class="bloc" aria-labelledby="nouvelle-section">
    <h2 id="nouvelle-section">Ajouter une section</h2>
    <form class="formulaire" method="post" action="<?= e($action) ?>" novalidate>
      <?= $csrf ?>
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resume('nouvelle', 'nouvelle')]) ?>
<?php $derniere = $sections === [] ? null : $sections[$nombre - 1]; ?>
      <?= $formulaire('nouvelle', ['nom' => '', 'debut' => $derniere !== null ? format_temps_precis($derniere['fin']) : '0:00,0', 'fin' => '', 'comptes' => '', 'note' => ''], 'nouvelle') ?>
      <button class="bouton bouton--plein bouton--56" type="submit" name="action" value="ajouter">Ajouter la section</button>
    </form>
  </section>
</div>
