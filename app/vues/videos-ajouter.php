<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\e;
use function Chores\url;

use const Chores\CHAMP_CSRF;

/**
 * Téléversement d'une vidéo (non dessiné ; spec § 2 et 7) : chanson, titre, note, « Filmée en miroir », fichier,
 * limites écrites, conseils. Sans JavaScript : formulaire classique. Avec JavaScript (televersement.js) : barre de
 * progression « 42 % · 31 Mo sur 74 Mo » et bouton Annuler.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, string> $chansons slug → titre
 * @var array{chanson: string, titre: string, note: string, en_miroir: bool} $valeurs
 * @var array<string, string> $erreurs id du champ → message
 * @var int $maxMo
 */
$minutes = max(1, (int) round($maxMo / 70));
$libelles = ['video-chanson' => 'Chanson', 'video-titre' => 'Titre', 'video-note' => 'Note', 'video-fichier' => 'Vidéo'];
$resume = [];
foreach ($erreurs as $id => $message) {
    $resume[$id] = ($libelles[$id] ?? 'Vidéo') . ' : ' . $message;
}
?>
<div class="contenu televersement">
  <h1 class="titre-ecran">Ajouter ma vidéo</h1>
  <p class="televersement__aide">Filme une section ou deux, pas la chanson entière : c’est plus léger à envoyer et plus facile à commenter.</p>
  <form class="formulaire" method="post" action="<?= e(url($config, '/videos')) ?>" enctype="multipart/form-data" novalidate data-televersement data-max-octets="<?= e($maxMo * 1024 * 1024) ?>">
    <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
    <div data-resume-erreurs>
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resume, 'titre' => count($resume) > 1 ? 'Ta vidéo n’est pas partie, il reste ' . count($resume) . ' choses à corriger :' : 'Ta vidéo n’est pas partie, il reste une chose à corriger :']) ?>
    </div>
    <?= composant($c, 'champ', [
        'type' => 'select', 'nom' => 'chanson', 'id' => 'video-chanson', 'libelle' => 'Chanson', 'requis' => true,
        'options' => ['' => 'Choisis la chanson'] + $chansons, 'valeur' => $valeurs['chanson'], 'erreur' => $erreurs['video-chanson'] ?? null,
    ]) ?>
    <?= composant($c, 'champ', [
        'type' => 'texte', 'nom' => 'titre', 'id' => 'video-titre', 'libelle' => 'Titre', 'requis' => true,
        'aide' => 'Par exemple le nom de la section filmée. 80 caractères au plus.',
        'valeur' => $valeurs['titre'], 'erreur' => $erreurs['video-titre'] ?? null, 'attributs' => ['maxlength' => '80', 'autocomplete' => 'off'],
    ]) ?>
    <?= composant($c, 'champ', [
        'type' => 'zone', 'nom' => 'note', 'id' => 'video-note', 'libelle' => 'Note pour les cousins',
        'aide' => '300 caractères au plus.', 'valeur' => $valeurs['note'], 'erreur' => $erreurs['video-note'] ?? null, 'attributs' => ['maxlength' => '300'],
    ]) ?>
    <?= composant($c, 'champ', [
        'type' => 'case', 'nom' => 'en_miroir', 'id' => 'video-miroir', 'libelle' => 'Filmée en miroir (face à toi, comme dans une glace)', 'coche' => $valeurs['en_miroir'],
    ]) ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= e($maxMo * 1024 * 1024) ?>">
    <?= composant($c, 'champ', [
        'type' => 'fichier', 'nom' => 'video', 'id' => 'video-fichier', 'libelle' => 'Vidéo', 'requis' => true,
        'aide' => "MP4, MOV ou WebM. {$maxMo} Mo au plus, soit environ {$minutes} minutes en 720p." . (isset($erreurs['video-fichier']) || $erreurs !== [] ? ' Choisis de nouveau le fichier : un navigateur ne le garde jamais après un refus.' : ''),
        'erreur' => $erreurs['video-fichier'] ?? null,
        'attributs' => ['accept' => 'video/mp4,video/quicktime,video/webm'],
    ]) ?>
    <details class="televersement__conseil">
      <summary class="televersement__resume">Sur iPhone : pour que tout le monde puisse lire ta vidéo</summary>
      <p>Les iPhone filment par défaut en « Haute efficacité » (HEVC), que beaucoup d’Android et de PC ne lisent pas. Avant de filmer : Réglages › Appareil photo › Formats › « Le plus compatible ».</p>
      <p>Si ta vidéo est déjà filmée, envoie-la quand même : Patrice la convertira.</p>
    </details>
    <div class="televersement__envoi">
      <button class="bouton bouton--plein bouton--56" type="submit" data-envoyer>Envoyer</button>
      <div class="televersement__progression" data-progression hidden>
        <progress class="televersement__barre" max="100" value="0" aria-label="Envoi de la vidéo" data-barre>0 %</progress>
        <p class="televersement__texte" data-texte-progression>0 %</p>
        <button class="bouton bouton--contour" type="button" data-annuler>Annuler l’envoi</button>
      </div>
      <p class="televersement__message" role="status" data-message></p>
    </div>
  </form>
</div>
