<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\date_paris;
use function Chores\e;
use function Chores\format_octets;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\CODECS;

/**
 * Vidéos en administration (non dessiné) : toutes les vidéos (cousins et références) avec taille et codec deviné,
 * Supprimer (confirmation en page), « Remplacer par une version convertie » (procédure : docs/VIDEOS.md).
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array<string, mixed>> $videos
 * @var ?array<string, mixed> $aSupprimer
 * @var int $espace
 * @var int $espaceMax
 * @var int $maxMo
 * @var ?int $erreurPour
 * @var ?string $erreur
 * @var ?string $confirmation
 */
$base = url($config, '/admin/videos');
$csrf = '<input type="hidden" name="' . e(CHAMP_CSRF) . '" value="' . e(champ_csrf($c)) . '">';
?>
<div class="contenu">
  <h1 class="titre-ecran">Vidéos</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
  <p>Espace utilisé : <?= e(format_octets($espace)) ?> sur <?= e(format_octets($espaceMax)) ?> permis.</p>
  <p>Une vidéo marquée HEVC ou « non reconnu » est peut-être illisible sur Android et sur PC : convertis-la sur ton PC (commande dans docs/VIDEOS.md), puis remplace-la ici. Sa page et ses commentaires restent.</p>
<?php if ($aSupprimer !== null): ?>
  <div class="admin__confirmation" role="alert" aria-labelledby="confirmer-video">
    <p id="confirmer-video"><strong>Supprimer « <?= e($aSupprimer['titre']) ?> » (<?= e($aSupprimer['chanson_titre']) ?>) ?</strong> Le fichier est effacé du serveur. On ne peut pas revenir en arrière.</p>
    <form class="admin__actions" method="post" action="<?= e($base . '/' . (int) $aSupprimer['id'] . '/supprimer') ?>">
      <?= $csrf ?>
      <button class="bouton bouton--plein" type="submit">Oui, supprimer la vidéo</button>
      <a class="bouton bouton--contour" href="<?= e($base) ?>">Non, la garder</a>
    </form>
  </div>
<?php endif; ?>
<?php if ($videos === []): ?>
  <p class="encart encart--pointille">Aucune vidéo pour l’instant.</p>
<?php endif; ?>
  <ul class="admin__liste">
<?php foreach ($videos as $v): ?>
<?php $id = (int) $v['id']; $champ = 'remplacer-' . $id; ?>
    <li class="admin__ligne admin__ligne--video" id="video-<?= e($id) ?>">
      <h2 class="admin__sous-titre"><?= e($v['chanson_titre']) ?> · <?= e($v['type'] === 'reference' ? 'vidéo de référence' : ($v['auteur'] ?? 'un cousin') . ' : ' . $v['titre']) ?></h2>
      <p><?= e(date_paris((string) $v['cree_le'])) ?> · <?= e(format_octets((int) $v['taille_o'])) ?> · format <?= e(CODECS[$v['codec_devine']] ?? 'non reconnu') ?> · <?= e(strtoupper(explode('/', (string) $v['mime'])[1] ?? '')) ?></p>
      <div class="admin__actions">
<?php if ($v['type'] === 'cousin'): ?>
        <a class="bouton bouton--contour" href="<?= e(url($config, '/videos/' . $id)) ?>">Voir<span class="visuellement-cache"> la vidéo <?= e($v['titre']) ?></span></a>
<?php else: ?>
        <a class="bouton bouton--contour" href="<?= e(url($config, '/admin/chansons/' . $v['chanson_slug'])) ?>">Fiche de la chanson<span class="visuellement-cache"> <?= e($v['chanson_titre']) ?></span></a>
<?php endif; ?>
        <a class="bouton bouton--contour" href="<?= e($base . '?supprimer=' . $id) ?>">Supprimer<span class="visuellement-cache"> la vidéo <?= e($v['titre']) ?></span></a>
      </div>
      <form class="formulaire" method="post" action="<?= e($base . '/' . $id . '/remplacer') ?>" enctype="multipart/form-data" novalidate>
        <?= $csrf ?>
<?php if ($erreurPour === $id && $erreur !== null): ?>
        <?= composant($c, 'resume-erreurs', ['erreurs' => [$champ => $erreur], 'titre' => 'La vidéo n’a pas été remplacée :']) ?>
<?php endif; ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= e($maxMo * 1024 * 1024) ?>">
        <?= composant($c, 'champ', [
            'type' => 'fichier', 'nom' => 'video', 'id' => $champ, 'libelle' => 'Remplacer « ' . $v['titre'] . ' » par une version convertie', 'requis' => true,
            'aide' => "MP4 (H.264) ou WebM, {$maxMo} Mo au plus.", 'erreur' => $erreurPour === $id ? $erreur : null,
            'attributs' => ['accept' => 'video/mp4,video/webm'],
        ]) ?>
        <button class="bouton bouton--plein" type="submit">Remplacer<span class="visuellement-cache"> la vidéo <?= e($v['titre']) ?></span></button>
      </form>
    </li>
<?php endforeach; ?>
  </ul>
</div>
