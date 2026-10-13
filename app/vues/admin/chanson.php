<?php

declare(strict_types=1);

use function Chores\champ_csrf;
use function Chores\composant;
use function Chores\date_paris;
use function Chores\e;
use function Chores\format_octets;
use function Chores\format_temps;
use function Chores\url;

use const Chores\CHAMP_CSRF;
use const Chores\Controleurs\STATUTS_CHANSON;
use const Chores\TYPES_LIENS;

/**
 * Fiche d'une chanson (administration) : titre, film, durée, tempo (« Taper le tempo » en JavaScript), statut,
 * à créer, ordre de passage, entrée et musique, danseurs ; liens sortants ; vidéo de référence.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var array<string, mixed> $chanson
 * @var array<string, mixed> $valeurs
 * @var array<string, string> $erreurs
 * @var list<array{id: int, prenom: string}> $cousins
 * @var list<array<string, mixed>> $liens
 * @var array<string, string> $lienSaisi
 * @var array<string, string> $erreursLien
 * @var ?array<string, mixed> $reference
 * @var ?string $erreurVideo
 * @var ?string $confirmation
 * @var bool $codecInconnu
 */
$slug = (string) $chanson['slug'];
$base = url($config, '/admin/chansons/' . $slug);
$csrf = '<input type="hidden" name="' . e(CHAMP_CSRF) . '" value="' . e(champ_csrf($c)) . '">';
$texte = static fn (string $nom, string $libelle, ?string $aide = null, array $attributs = []): string => composant($c, 'champ', [
    'type' => 'texte', 'nom' => $nom, 'id' => 'chanson-' . $nom, 'libelle' => $libelle, 'aide' => $aide,
    'erreur' => $erreurs[$nom] ?? null, 'valeur' => (string) $valeurs[$nom], 'attributs' => $attributs + ['autocomplete' => 'off'],
]);
$resume = [];
foreach ($erreurs as $nom => $message) {
    // Le lien du résumé vise un champ qui prend le focus (la première liste des danseurs, pas le groupe).
    $resume[$nom === 'danseurs' && $cousins !== [] ? 'danseur-' . $cousins[0]['id'] : 'chanson-' . $nom] = $message;
}
$resumeLien = [];
foreach ($erreursLien as $nom => $message) {
    $resumeLien['lien-' . $nom] = $message;
}
$maxMo = (int) $config['TAILLE_MAX_UPLOAD_MO'];
?>
<div class="contenu admin">
  <h1 class="titre-ecran"><?= e($chanson['titre']) ?></h1>
  <nav class="admin__navigation" aria-label="Cette chanson">
    <a class="bouton bouton--contour" href="<?= e($base . '/sections') ?>">Sections</a>
    <a class="bouton bouton--contour" href="<?= e($base . '/placements') ?>">Placements</a>
    <a class="bouton bouton--contour" href="<?= e(url($config, '/chanson/' . $slug)) ?>">Voir la page des cousins</a>
  </nav>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if ($codecInconnu): ?>
  <p class="avertissement">Format de la vidéo non reconnu (ni H.264 ni HEVC) : vérifie sur un téléphone Android et sur un iPhone qu’elle se lit.</p>
<?php endif; ?>

  <section class="bloc" aria-labelledby="fiche">
    <h2 id="fiche">Fiche</h2>
    <form class="formulaire" method="post" action="<?= e($base) ?>" novalidate>
      <?= $csrf ?>
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resume]) ?>
      <?= $texte('titre', 'Titre', null, ['maxlength' => '80']) ?>
      <?= $texte('film', 'Film', 'Vide : « [film à confirmer] ».', ['maxlength' => '80']) ?>
      <?= $texte('duree', 'Durée', 'Minutes et secondes, par exemple 4:04. Vide : « [durée inconnue] ».', ['maxlength' => '8', 'inputmode' => 'numeric']) ?>
      <div class="admin__tempo">
        <?= $texte('bpm', 'Tempo (temps par minute)', 'Vide : le décompte part à 120 avec « tempo à régler ».', ['maxlength' => '3', 'inputmode' => 'numeric', 'data-tempo' => '']) ?>
        <div class="admin__taper" data-js-seulement hidden>
          <button class="bouton bouton--secondaire" type="button" data-taper-tempo>Taper le tempo</button>
          <p class="champ__aide" role="status" data-tempo-etat>Touche le bouton à chaque temps de la musique : la moyenne des 8 derniers s’écrit dans le champ.</p>
        </div>
      </div>
      <?= composant($c, 'champ', ['type' => 'case', 'nom' => 'bpm_verifie', 'id' => 'chanson-bpm_verifie', 'libelle' => 'Tempo vérifié (sinon : « tempo à confirmer »)', 'coche' => (bool) $valeurs['bpm_verifie']]) ?>
      <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'statut', 'id' => 'chanson-statut', 'libelle' => 'Statut', 'requis' => true, 'options' => STATUTS_CHANSON, 'valeur' => (string) $valeurs['statut'], 'erreur' => $erreurs['statut'] ?? null]) ?>
      <?= composant($c, 'champ', ['type' => 'case', 'nom' => 'a_creer', 'id' => 'chanson-a_creer', 'libelle' => 'Choré à créer (encart « Qui s’en occupe ? »)', 'coche' => (bool) $valeurs['a_creer']]) ?>
      <?= $texte('ordre_passage', 'Ordre de passage', 'De 1 à 99. Vide : pas encore fixé.', ['maxlength' => '2', 'inputmode' => 'numeric']) ?>
      <?= $texte('entree_qui', 'Qui entre', null, ['maxlength' => '200']) ?>
      <?= $texte('entree_dou', 'D’où', null, ['maxlength' => '200']) ?>
      <?= $texte('musique_qui', 'Qui lance la musique', null, ['maxlength' => '200']) ?>
      <?= $texte('duree_coupee', 'Durée coupée', 'Durée de la musique coupée, par exemple 2:30.', ['maxlength' => '8', 'inputmode' => 'numeric']) ?>
      <?= composant($c, 'champ', ['type' => 'zone', 'nom' => 'note', 'id' => 'chanson-note', 'libelle' => 'Note', 'aide' => 'Lue par tous sur la page de la chanson. 500 caractères au plus.', 'valeur' => (string) $valeurs['note'], 'erreur' => $erreurs['note'] ?? null, 'attributs' => ['maxlength' => '500']]) ?>
      <fieldset class="admin__danseurs" id="chanson-danseurs"<?= isset($erreurs['danseurs']) ? ' aria-describedby="chanson-danseurs-erreur"' : '' ?>>
        <legend class="champ__etiquette">Danseurs</legend>
<?php if (isset($erreurs['danseurs'])): ?>
        <p class="champ__erreur" id="chanson-danseurs-erreur"><span class="champ__erreur-mot">Erreur :</span> <?= e($erreurs['danseurs']) ?></p>
<?php endif; ?>
<?php foreach ($cousins as $cousin): ?>
        <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'danseur_' . $cousin['id'], 'id' => 'danseur-' . $cousin['id'], 'libelle' => $cousin['prenom'], 'sansMention' => true, 'options' => ['' => 'ne danse pas', 'danse' => 'danse', 'en_attente' => 'en attente'], 'valeur' => (string) ($valeurs['danseurs'][$cousin['id']] ?? '')]) ?>
<?php endforeach; ?>
      </fieldset>
      <button class="bouton bouton--plein bouton--56" type="submit">Enregistrer la fiche</button>
    </form>
  </section>

  <section class="bloc" aria-labelledby="liens">
    <h2 id="liens">Liens (YouTube et autres)</h2>
<?php if ($liens === []): ?>
    <p>Aucun lien.</p>
<?php else: ?>
    <ul class="admin__liste">
<?php foreach ($liens as $l): ?>
      <li class="admin__ligne">
        <span><strong><?= e($l['titre']) ?></strong> · <?= e(TYPES_LIENS[$l['type']] ?? $l['type']) ?><?= $l['duree_s'] !== null ? ' · ' . e(format_temps((int) $l['duree_s'])) : '' ?><?= $l['en_miroir'] !== null && (int) $l['en_miroir'] === 1 ? ' · en miroir' : '' ?><br><span class="admin__url"><?= e($l['url']) ?></span></span>
        <form method="post" action="<?= e($base . '/liens') ?>">
          <?= $csrf ?>
          <input type="hidden" name="lien" value="<?= e($l['id']) ?>">
          <button class="bouton bouton--contour" type="submit" name="action" value="supprimer">Supprimer <span class="visuellement-cache">le lien <?= e($l['titre']) ?></span></button>
        </form>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <form class="formulaire" method="post" action="<?= e($base . '/liens') ?>" novalidate>
      <?= $csrf ?>
      <h3 class="admin__sous-titre">Ajouter un lien</h3>
      <?= composant($c, 'resume-erreurs', ['erreurs' => $resumeLien]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'url', 'id' => 'lien-url', 'libelle' => 'Adresse', 'requis' => true, 'aide' => 'Commence par https://. Lien sortant seulement : la vidéo n’est jamais intégrée au site.', 'valeur' => $lienSaisi['url'] ?? '', 'erreur' => $erreursLien['url'] ?? null, 'attributs' => ['maxlength' => '300', 'inputmode' => 'url', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'titre_lien', 'id' => 'lien-titre_lien', 'libelle' => 'Titre', 'requis' => true, 'valeur' => $lienSaisi['titre'] ?? '', 'erreur' => $erreursLien['titre_lien'] ?? null, 'attributs' => ['maxlength' => '120', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'texte', 'nom' => 'duree_lien', 'id' => 'lien-duree_lien', 'libelle' => 'Durée', 'aide' => 'Par exemple 9:41.', 'valeur' => $lienSaisi['duree'] ?? '', 'erreur' => $erreursLien['duree_lien'] ?? null, 'attributs' => ['maxlength' => '8', 'inputmode' => 'numeric', 'autocomplete' => 'off']]) ?>
      <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'type', 'id' => 'lien-type', 'libelle' => 'Type', 'requis' => true, 'options' => TYPES_LIENS, 'valeur' => $lienSaisi['type'] ?? 'tutoriel', 'erreur' => $erreursLien['type'] ?? null]) ?>
      <?= composant($c, 'champ', ['type' => 'select', 'nom' => 'miroir', 'id' => 'lien-miroir', 'libelle' => 'Filmé en miroir', 'sansMention' => true, 'options' => ['' => 'on ne sait pas', '1' => 'oui', '0' => 'non'], 'valeur' => $lienSaisi['miroir'] ?? '', 'erreur' => $erreursLien['miroir'] ?? null]) ?>
      <button class="bouton bouton--plein" type="submit" name="action" value="ajouter">Ajouter le lien</button>
    </form>
  </section>

  <section class="bloc" aria-labelledby="reference">
    <h2 id="reference">Vidéo de référence</h2>
<?php if ($reference !== null): ?>
    <p>Déposée le <?= e(date_paris((string) $reference['cree_le'])) ?> · <?= e(format_octets((int) $reference['taille_o'])) ?> · format <?= e(['h264' => 'H.264', 'hevc' => 'HEVC', 'inconnu' => 'non reconnu'][$reference['codec_devine']] ?? 'non reconnu') ?>.</p>
    <form method="post" action="<?= e($base . '/reference') ?>">
      <?= $csrf ?>
      <button class="bouton bouton--contour" type="submit" name="action" value="retirer">Retirer la vidéo de référence</button>
    </form>
<?php else: ?>
    <p>Pas encore de vidéo de référence : la page de la chanson affiche « Pas encore de vidéo de référence ».</p>
<?php endif; ?>
    <form class="formulaire" method="post" action="<?= e($base . '/reference') ?>" enctype="multipart/form-data" novalidate>
      <?= $csrf ?>
<?php if ($erreurVideo !== null): ?>
      <?= composant($c, 'resume-erreurs', ['erreurs' => ['reference-video' => $erreurVideo], 'titre' => 'La vidéo n’a pas été déposée :']) ?>
<?php endif; ?>
      <?= composant($c, 'champ', [
          'type' => 'fichier', 'nom' => 'video', 'id' => 'reference-video', 'libelle' => $reference !== null ? 'Remplacer par une autre vidéo' : 'Vidéo de référence', 'requis' => true,
          'aide' => "MP4 en H.264, déjà converti sur ton PC (commande dans docs/ADMIN.md), {$maxMo} Mo au plus. Rien n’est converti sur le serveur.",
          'erreur' => $erreurVideo, 'attributs' => ['accept' => 'video/mp4,video/webm'],
      ]) ?>
      <button class="bouton bouton--plein" type="submit">Déposer la vidéo</button>
    </form>
  </section>
</div>
