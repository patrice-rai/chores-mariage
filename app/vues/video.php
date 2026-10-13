<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\date_paris;
use function Chores\de;
use function Chores\e;
use function Chores\format_octets;
use function Chores\format_temps;
use function Chores\url;
use function Chores\video_peut_etre_illisible;

use const Chores\CHAMP_CSRF;
use const Chores\MOT_FORMAT_IPHONE;

/**
 * Une vidéo d'un cousin (variante de 1p, spec § 2 et 4) : lecteur simple (contrôles natifs ; avec JavaScript,
 * vitesses et miroir), commentaires horodatés qui positionnent la vidéo, formulaire « à {temps} », suppression par
 * l'auteur ou par Patrice avec confirmation en page, « Garder sur mon téléphone ».
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $video
 * @var string $src
 * @var ?int $instant secondes demandées par ?t= (sans JavaScript : fragment #t= de la vidéo)
 * @var list<array{id: int, cousin_id: ?int, auteur: string, temps: ?float, texte: string, cree_le: string}> $commentaires
 * @var bool $estAuteur
 * @var int $moi
 * @var bool $patrice
 * @var bool $confirmerSuppression
 * @var ?array{id: int, auteur: string, temps: ?float, texte: string} $commentaireASupprimer
 * @var array<string, string> $erreurs
 * @var array{temps: string, texte: string} $saisie
 * @var ?string $confirmation
 * @var ?string $confirmationCommentaire affichée dans « Commentaires »
 */
$id = (int) $video['id'];
$page = url($config, '/videos/' . $id);
$miroir = (int) $video['en_miroir'] === 1;
$illisible = video_peut_etre_illisible($video);
$auteur = (string) ($video['auteur'] ?? 'Un cousin');
$peutSupprimer = $estAuteur || $patrice;
$resume = [];
foreach ($erreurs as $champ => $message) {
    $resume[$champ] = ($champ === 'commentaire-temps' ? 'Instant' : 'Commentaire') . ' : ' . $message;
}
?>
<div class="contenu contenu--large page-video">
  <div>
    <h1 class="titre-chanson"><?= e($auteur) ?> · <?= e($video['titre']) ?></h1>
    <p class="page-chanson__infos"><a href="<?= e(url($config, '/chanson/' . $video['chanson_slug'])) ?>"><?= e($video['chanson_titre']) ?></a> · postée le <?= e(date_paris((string) $video['cree_le'])) ?> · <?= e($video['duree_s'] !== null ? format_temps((float) $video['duree_s']) : 'durée inconnue') ?> · <?= e(format_octets((int) $video['taille_o'])) ?></p>
<?php if ($video['note'] !== null): ?>
    <p class="page-video__note"><?= e($video['note']) ?></p>
<?php endif; ?>
  </div>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($illisible && $estAuteur): ?>
  <div class="encart encart--orange" role="note">
    <p class="encart__titre">Ta vidéo est peut-être en HEVC</p>
    <p class="encart__texte">Sur iPhone, règle Réglages › Appareil photo › Formats › « Le plus compatible » pour les prochaines. Si les autres ne peuvent pas la lire, Patrice la convertira.</p>
  </div>
<?php elseif ($illisible): ?>
  <p class="carte-video__format"><?= e(MOT_FORMAT_IPHONE) ?>.</p>
<?php endif; ?>

  <section class="lecteur-simple<?= $miroir ? ' lecteur-simple--miroir' : '' ?>" id="lecteur-video" aria-label="Lecteur : vidéo <?= e(de($auteur)) ?>" data-lecteur-simple<?= $estAuteur && $video['duree_s'] === null ? ' data-poser-duree="' . e($page . '/duree') . '"' : '' ?>>
    <div class="lecteur-simple__ecran">
      <video class="lecteur-simple__video" src="<?= e($src . ($instant !== null ? '#t=' . $instant : '')) ?>" controls playsinline preload="metadata" aria-label="Vidéo <?= e(de($auteur)) ?> : <?= e($video['titre']) ?> (danse, sans paroles)" data-video></video>
      <p class="lecteur__badge-miroir" data-badge-miroir<?= $miroir ? '' : ' hidden' ?>>Miroir activé</p>
    </div>
<?php if ($miroir): ?>
    <p class="lecteur-simple__note">Filmée en miroir par <?= e($auteur) ?> : elle se lit comme dans une glace.</p>
<?php endif; ?>
    <p class="lecteur-simple__infos" data-js-seulement hidden><span data-horloge>0:00</span></p>
    <div class="lecteur__rangee lecteur__rangee--trois" role="group" aria-label="Vitesse" data-js-seulement hidden>
<?php foreach ([0.5, 0.75, 1.0] as $v): ?>
      <button class="commande commande--choix commande--vitesse" type="button" data-vitesse="<?= e($v) ?>" aria-pressed="<?= $v === 1.0 ? 'true' : 'false' ?>"><?= e(str_replace('.', ',', (string) $v)) ?> ×<span class="commande__coche" aria-hidden="true"> ✓</span></button>
<?php endforeach; ?>
    </div>
    <div class="lecteur__rangee lecteur__rangee--deux" data-js-seulement hidden>
      <button class="commande commande--choix commande--double" type="button" data-action="miroir" aria-pressed="<?= $miroir ? 'true' : 'false' ?>">Miroir <span class="commande__etat" data-etat><?= $miroir ? 'activé' : 'désactivé' ?></span></button>
    </div>
    <p class="visuellement-cache" aria-live="polite" data-annonce></p>
    <?= composant($c, 'garder', ['id' => 'garder-video', 'url' => $src, 'taille' => (int) $video['taille_o'], 'titre' => 'Vidéo ' . de($auteur) . ' · ' . $video['titre'] . ' (' . $video['chanson_titre'] . ')', 'page' => $page]) ?>
  </section>

  <section class="page-video__commentaires" id="commentaires" aria-labelledby="commentaires-titre">
    <h2 class="sections__titre" id="commentaires-titre">Commentaires</h2>
<?php if ($confirmationCommentaire !== null): ?>
    <?= composant($c, 'confirmation', ['texte' => $confirmationCommentaire]) ?>
<?php endif; ?>
<?php if ($commentaireASupprimer !== null): ?>
    <div class="admin__confirmation" role="alert" aria-labelledby="confirmer-commentaire">
      <p id="confirmer-commentaire"><strong>Supprimer ce commentaire <?= e(de($commentaireASupprimer['auteur'])) ?> ?</strong> « <?= e($commentaireASupprimer['texte']) ?> »</p>
      <form class="admin__actions" method="post" action="<?= e($page . '/commentaires/' . $commentaireASupprimer['id'] . '/supprimer') ?>">
        <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
        <button class="bouton bouton--plein" type="submit">Oui, supprimer le commentaire</button>
        <a class="bouton bouton--contour" href="<?= e($page . '#commentaires') ?>">Non, le garder</a>
      </form>
    </div>
<?php endif; ?>
<?php if ($commentaires === []): ?>
    <p class="sections__aide">Pas encore de commentaire. Mets la vidéo en pause au bon moment et dis ce que tu vois.</p>
<?php endif; ?>
    <div class="page-video__liste">
<?php foreach ($commentaires as $m): ?>
      <?= composant($c, 'commentaire', [
          'id' => 'commentaire-' . $m['id'],
          'temps' => $m['temps'],
          'auteur' => $m['auteur'],
          'texte' => $m['texte'],
          'href' => $page . ($m['temps'] !== null ? '?t=' . (int) floor($m['temps']) : '') . '#lecteur-video',
          'hrefSupprimer' => $m['cousin_id'] === $moi || $patrice ? $page . '?supprimer-commentaire=' . $m['id'] . '#commentaires' : null,
      ]) ?>
<?php endforeach; ?>
    </div>

    <form class="formulaire page-video__formulaire" method="post" action="<?= e($page . '/commentaires#commentaires') ?>" novalidate data-commentaire>
      <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
      <h3 class="admin__sous-titre">Ajouter un commentaire</h3>
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resume, 'titre' => 'Ton commentaire n’est pas parti :']) ?>
      <div class="page-video__instant">
        <?= composant($c, 'champ', [
            'type' => 'texte', 'nom' => 'temps', 'id' => 'commentaire-temps', 'libelle' => 'À quel instant',
            'aide' => 'En minutes et secondes, par exemple 0:42, ou en secondes : 42. Vide : un commentaire sur toute la vidéo.',
            'valeur' => $saisie['temps'], 'erreur' => $erreurs['commentaire-temps'] ?? null,
            'attributs' => ['maxlength' => '8', 'autocomplete' => 'off'],
        ]) ?>
        <button class="bouton bouton--contour page-video__position" type="button" data-prendre-position data-js-seulement hidden>Prendre la position</button>
      </div>
      <?= composant($c, 'champ', [
          'type' => 'zone', 'nom' => 'texte', 'id' => 'commentaire-texte', 'libelle' => 'Ton commentaire', 'requis' => true,
          'aide' => '200 caractères au plus.', 'valeur' => $saisie['texte'], 'erreur' => $erreurs['commentaire-texte'] ?? null,
          'attributs' => ['maxlength' => '200'],
      ]) ?>
      <button class="bouton bouton--plein bouton--56" type="submit">Commenter</button>
    </form>
  </section>

<?php if ($peutSupprimer): ?>
  <section class="page-video__suppression" aria-labelledby="supprimer-titre">
    <h2 class="sections__titre" id="supprimer-titre"><?= $estAuteur ? 'Supprimer ma vidéo' : 'Supprimer la vidéo (Patrice)' ?></h2>
<?php if ($confirmerSuppression): ?>
    <div class="admin__confirmation" role="alert" aria-labelledby="confirmer-video">
      <p id="confirmer-video"><strong>Supprimer « <?= e($video['titre']) ?> » ?</strong> Le fichier est effacé du serveur et ses commentaires disparaissent. On ne peut pas revenir en arrière.</p>
      <form class="admin__actions" method="post" action="<?= e($page . '/supprimer') ?>">
        <input type="hidden" name="<?= e(CHAMP_CSRF) ?>" value="<?= e(champ_csrf($c)) ?>">
        <button class="bouton bouton--plein" type="submit">Oui, supprimer la vidéo</button>
        <a class="bouton bouton--contour" href="<?= e($page . '#supprimer-titre') ?>">Non, la garder</a>
      </form>
    </div>
<?php else: ?>
    <a class="bouton bouton--contour" href="<?= e($page . '?supprimer=1#supprimer-titre') ?>"><?= $estAuteur ? 'Supprimer ma vidéo' : 'Supprimer la vidéo' ?></a>
<?php endif; ?>
  </section>
<?php endif; ?>
</div>
