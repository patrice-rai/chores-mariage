<?php

declare(strict_types=1);

use function Chores\e;
use function Chores\format_comptes;
use function Chores\format_duree;
use function Chores\format_duree_section;
use function Chores\format_temps;
use function Chores\format_vitesse;

/**
 * Lecteur de la vidéo de référence (spec § 4, maquette 1e à 1l). Sans JavaScript : <video controls> et les liens
 * (Plein écran, Section suivante, Quitter). Avec JavaScript (lecteur.js) : les contrôles natifs sont masqués au
 * profit des commandes de 56 px (72/60 en répétition) marquées data-js-seulement, révélées par le script.
 * $illustration (page /gabarits) montre un état figé : commandes visibles, aplat à la place de la vidéo, aucun
 * comportement (la page l'enveloppe dans un bloc inert).
 * @var string $id
 * @var array{cle: string, titre: string, duree_s: ?int, bpm: ?int} $chanson
 * @var ?string $video adresse de la vidéo de référence, null si Patrice ne l'a pas encore déposée
 * @var list<array{nom: string, debut: float, fin: float, comptes: int}> $sections
 * @var int $courante
 * @var string $mode « page » ou « repetition »
 * @var string $hrefSection début d'adresse du mode répétition (« …/repeter?section= ») ; le numéro est ajouté
 * @var ?string $hrefQuitter (repetition)
 * @var ?array{vitesse?: float, miroir?: bool, boucle?: bool, lecture?: string, voile?: ?string, chiffre?: int, temps?: float} $illustration
 */
$mode ??= 'page';
$hrefQuitter ??= null;
$illustration ??= null;
$fige = $illustration !== null;
$repetition = $mode === 'repetition';
$section = $sections[$courante] ?? null;
$nombre = count($sections);
$vitesse = (float) ($illustration['vitesse'] ?? 1);
$miroir = (bool) ($illustration['miroir'] ?? false);
$boucle = (bool) ($illustration['boucle'] ?? false);
$lecture = (string) ($illustration['lecture'] ?? 'Lecture');
$voile = $illustration['voile'] ?? null;
$sansVideo = $video === null;
$jsSeulement = $fige ? '' : ' data-js-seulement hidden';
$inactif = $sansVideo ? ' disabled' : '';
$suivante = $nombre > 0 ? ($courante + 1) % $nombre : 0;
$statut = ($miroir ? 'Miroir' : 'Pas de miroir') . ' · ' . format_vitesse($vitesse) . ($boucle ? ' · Boucle' : '');
$tempoConnu = $chanson['bpm'] !== null;
$donnees = $fige ? '' : sprintf(
    ' data-lecteur data-cle="%s" data-bpm="%s" data-courante="%d" data-mode="%s" data-lien-section="%s" data-sections="%s"',
    e($chanson['cle']),
    e($chanson['bpm'] ?? ''),
    $courante,
    e($mode),
    e($hrefSection),
    e((string) json_encode(array_map(static fn (array $s): array => [
        'nom' => $s['nom'], 'debut' => $s['debut'], 'fin' => $s['fin'], 'comptes' => $s['comptes'],
    ], $sections), JSON_UNESCAPED_UNICODE))
);
$commande = static fn (string $classe): string => 'commande ' . $classe;
?>
<section class="lecteur lecteur--<?= e($mode) ?><?= $miroir ? ' lecteur--miroir' : '' ?>" id="<?= e($id) ?>" aria-label="Lecteur : <?= e($chanson['titre']) ?>"<?= $donnees ?>>
  <div class="lecteur__ecran<?= $sansVideo || $fige ? ' lecteur__ecran--aplat' : '' ?>">
<?php if (!$sansVideo && !$fige): ?>
    <video class="lecteur__video" src="<?= e($video) ?>" controls playsinline preload="metadata" aria-label="Vidéo de référence : <?= e($chanson['titre']) ?> (musique, sans paroles)" data-video></video>
<?php else: ?>
    <div class="lecteur__aplat">
      <p class="lecteur__aplat-titre"><?= e($chanson['titre']) ?></p>
<?php if ($sansVideo): ?>
      <p class="lecteur__aplat-texte">Pas encore de vidéo de référence pour cette chanson.</p>
<?php endif; ?>
    </div>
<?php endif; ?>
    <p class="lecteur__badge-miroir" data-badge-miroir<?= $miroir ? '' : ' hidden' ?>>Miroir activé</p>
<?php if ($repetition): ?>
    <div class="lecteur__surimpression">
      <div class="lecteur__section-info">
        <p class="lecteur__section-nom" data-nom-section><?= e($section['nom'] ?? 'Chanson entière') ?></p>
        <p class="lecteur__section-comptes" data-comptes-section><?= $section !== null ? e(format_comptes($section['comptes']) . ' · ' . format_duree_section($section['debut'], $section['fin'])) : '' ?></p>
      </div>
      <p class="lecteur__statut" data-statut<?= $jsSeulement ?>><?= e($statut) ?></p>
    </div>
<?php endif; ?>
    <div class="lecteur__voile" data-voile-chargement<?= $voile === 'chargement' || $voile === 'garde' ? '' : ' hidden' ?>>
      <p class="lecteur__voile-texte" data-voile-texte><?= $voile === 'garde' ? 'Vidéo gardée sur ton téléphone' : 'Chargement de la vidéo…' ?></p>
    </div>
    <div class="lecteur__voile lecteur__voile--decompte" data-voile-decompte<?= $voile === 'decompte' ? '' : ' hidden' ?>>
      <p class="decompte__chiffre lecteur__chiffre" aria-hidden="true" data-decompte-chiffre><?= e($illustration['chiffre'] ?? '') ?></p>
      <p class="decompte__texte lecteur__decompte-texte" data-decompte-texte><?= $voile === 'decompte' ? 'Décompte : ' . e($illustration['chiffre'] ?? 5) : '' ?></p>
    </div>
    <div class="lecteur__progression" aria-hidden="true"<?= $jsSeulement ?>>
      <span class="lecteur__borne" data-borne></span>
      <span class="lecteur__position<?= $fige ? ' p-' . e((int) round(min(100, 100 * ($illustration['temps'] ?? 0) / max(1, (int) $chanson['duree_s'])) / 5)) : '' ?>" data-position></span>
    </div>
  </div>
<?php if (!$repetition && !$sansVideo): ?>
  <p class="lecteur__infos" data-infos<?= $jsSeulement ?>><span data-horloge><?= e(format_temps($illustration['temps'] ?? 0)) ?></span> / <?= e(format_duree($chanson['duree_s'])) ?> · <span data-nom-section><?= e($section['nom'] ?? 'Chanson entière') ?></span></p>
<?php endif; ?>
  <p class="visuellement-cache" aria-live="polite" data-annonce></p>
  <p class="lecteur__message" role="status" data-message></p>
  <div class="lecteur__commandes">
    <div class="lecteur__rangee lecteur__rangee--deux">
      <button class="<?= $commande('commande--lecture') ?>" type="button" data-action="lecture"<?= $jsSeulement ?><?= $inactif ?>><?= e($lecture) ?></button>
<?php if ($repetition): ?>
<?php if ($nombre >= 2): ?>
      <a class="<?= $commande('commande--suivante') ?>" href="<?= e($hrefSection . ($suivante + 1)) ?>" data-lien-suivante data-sans-js>Section suivante</a>
      <button class="<?= $commande('commande--suivante') ?>" type="button" data-action="suivante" data-js-seulement hidden>Section suivante</button>
<?php endif; ?>
<?php else: ?>
      <a class="<?= $commande('commande--plein-ecran') ?>" href="<?= e($hrefSection . ($courante + 1)) ?>" data-lien-repetition>Plein écran <span class="visuellement-cache">(mode répétition)</span></a>
<?php endif; ?>
    </div>
    <div class="lecteur__rangee lecteur__rangee--trois" role="group" aria-label="Vitesse"<?= $jsSeulement ?>>
<?php foreach ([0.5, 0.75, 1.0] as $v): ?>
      <button class="<?= $commande('commande--choix commande--vitesse') ?>" type="button" data-vitesse="<?= e($v) ?>" aria-pressed="<?= abs($v - $vitesse) < 0.01 ? 'true' : 'false' ?>"<?= $inactif ?>><?= e(format_vitesse($v)) ?><span class="commande__coche" aria-hidden="true"> ✓</span></button>
<?php endforeach; ?>
    </div>
    <div class="lecteur__rangee <?= $repetition ? 'lecteur__rangee--quatre' : 'lecteur__rangee--deux' ?>">
      <button class="<?= $commande('commande--choix commande--double') ?>" type="button" data-action="miroir" aria-pressed="<?= $miroir ? 'true' : 'false' ?>"<?= $jsSeulement ?><?= $inactif ?>>Miroir <span class="commande__etat" data-etat><?= $miroir ? 'activé' : 'désactivé' ?></span></button>
      <button class="<?= $commande('commande--choix commande--double') ?>" type="button" data-action="boucle" aria-pressed="<?= $boucle ? 'true' : 'false' ?>"<?= $jsSeulement ?><?= $inactif !== '' || $nombre === 0 ? ' disabled' : '' ?>>Boucle <span class="commande__etat" data-etat><?= $boucle ? 'activée' : 'désactivée' ?></span></button>
<?php if ($repetition): ?>
      <button class="<?= $commande('commande--choix commande--double') ?>" type="button" data-action="plein-ecran" aria-pressed="false" data-js-seulement hidden>Plein écran <span class="commande__etat" data-etat>désactivé</span></button>
      <a class="<?= $commande('commande--choix') ?>" href="<?= e((string) $hrefQuitter) ?>" data-action="quitter">Quitter</a>
<?php endif; ?>
    </div>
<?php if ($sansVideo): ?>
    <p class="lecteur__note">Les commandes s’activeront quand Patrice aura déposé la vidéo de référence. Les sections et tes niveaux marchent déjà.</p>
<?php endif; ?>
<?php if ($nombre === 0): ?>
    <p class="lecteur__note">Pas de section encore : la boucle attend le découpage de Patrice.</p>
<?php endif; ?>
<?php if (!$tempoConnu): ?>
    <p class="lecteur__note">Tempo à régler : le décompte part à 120 temps par minute.</p>
<?php endif; ?>
<?php if (!$fige && !$sansVideo): ?>
    <details class="lecteur__raccourcis" data-js-seulement hidden>
      <summary class="lecteur__raccourcis-titre">Raccourcis clavier</summary>
      <button class="bouton bouton--choix" type="button" data-action="raccourcis" aria-pressed="true">Raccourcis <span data-etat>activés</span></button>
      <dl class="lecteur__touches">
        <div><dt>Espace</dt><dd>lecture ou pause</dd></div>
        <div><dt>M</dt><dd>miroir</dd></div>
        <div><dt>B</dt><dd>boucle</dd></div>
        <div><dt>1, 2, 3</dt><dd>vitesse 0,5 ×, 0,75 ×, 1 ×</dd></div>
        <div><dt>N, P</dt><dd>section suivante, précédente</dd></div>
<?php if ($repetition): ?>
        <div><dt>Échap</dt><dd>quitter le mode répétition</dd></div>
<?php endif; ?>
      </dl>
    </details>
<?php endif; ?>
  </div>
</section>
