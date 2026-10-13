<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\date_en_mots;
use function Chores\e;
use function Chores\lien_sortant_valide;
use function Chores\url;

/**
 * Répétitions (maquette 1n, 1o) : « Proposer des dates » (sondage externe ou encart de repli), à venir (décidées,
 * proposées, annulées) avec « Je viens / Je ne peux pas », passées (présents et absents constatés par Patrice, ou
 * les réponses s'il n'a rien noté ; compte rendu).
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{repetition: array<string, mixed>, bilan: array<string, list<string>>, maReponse: ?string, ouverte: bool}> $avenir
 * @var list<array{repetition: array<string, mixed>, bilan: array<string, list<string>>, maReponse: ?string, ouverte: bool}> $passees
 * @var string $aujourdhui
 * @var ?string $lienSondage
 * @var ?string $confirmation
 */
$liste = static fn (array $prenoms): string => implode(', ', $prenoms);
$carte = static function (array $ligne, bool $passee) use ($c, $config, $aujourdhui, $liste): string {
    $r = $ligne['repetition'];
    $b = $ligne['bilan'];
    $id = 'repetition-' . (int) $r['id'];
    $jour = date_en_mots((string) $r['date'], $aujourdhui);
    $statut = match (true) {
        $r['statut'] === 'annulee' => 'Annulée',
        $passee && $r['statut'] === 'decidee' => 'Décidée, date passée',
        $passee && $r['statut'] === 'proposee' => 'Proposée, jamais fixée',
        $r['statut'] === 'decidee' => 'Décidée',
        $r['statut'] === 'passee' => 'Passée',
        default => 'Proposée, date pas encore fixée',
    };
    $details = array_filter([$r['heure'], $r['lieu']], static fn ($v): bool => $v !== null && $v !== '');
    $sansReponse = $b['oui'] === [] && $b['non'] === [] && $b['peut_etre'] === [];
    $constate = $b['presents'] !== [] || $b['absents'] !== [];
    ob_start();
    ?>
    <article class="repetition-carte<?= $passee ? ' repetition-carte--passee' : '' ?><?= $r['statut'] === 'decidee' && !$passee ? ' repetition-carte--decidee' : '' ?>" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-titre">
      <h3 class="repetition-carte__titre" id="<?= e($id) ?>-titre"><?= e(ucfirst($jour)) ?></h3>
      <p class="repetition-carte__statut"><strong class="repetition-carte__mot"><?= e($statut) ?></strong><?= $details !== [] ? ' · ' . e(implode(' · ', $details)) : '' ?></p>
<?php if ($r['travail'] !== null): ?>
      <p>On travaille : <?= e($r['travail']) ?></p>
<?php endif; ?>
<?php if ($r['note'] !== null): ?>
      <p class="repetition-carte__note"><?= e($r['note']) ?></p>
<?php endif; ?>
<?php if ($r['statut'] === 'annulee'): ?>
      <p>Pas de répétition ce jour-là.</p>
<?php elseif ($passee && $constate): ?>
      <ul class="repetition-carte__reponses">
        <li><strong class="repetition-carte__presents">Présents :</strong> <?= e($b['presents'] === [] ? 'personne' : $liste($b['presents'])) ?></li>
        <li><strong>Absents :</strong> <?= e($b['absents'] === [] ? 'personne' : $liste($b['absents'])) ?></li>
<?php if ($b['pas_note'] !== []): ?>
        <li><strong>Pas noté :</strong> <?= e($liste($b['pas_note'])) ?></li>
<?php endif; ?>
      </ul>
<?php elseif ($sansReponse): ?>
      <p class="repetition-carte__reponses"><?= $passee ? 'Personne n’avait répondu.' : 'Personne n’a encore répondu. Sois la première ou le premier.' ?></p>
<?php else: ?>
      <ul class="repetition-carte__reponses">
<?php if ($b['oui'] !== []): ?>
        <li><strong><?= $passee ? 'Avaient dit venir' : 'Viennent' ?> :</strong> <?= e($liste($b['oui'])) ?></li>
<?php endif; ?>
<?php if ($b['peut_etre'] !== []): ?>
        <li><strong><?= $r['statut'] === 'proposee' ? 'Dispos d’après le sondage WhatsApp' : 'Peut-être' ?> :</strong> <?= e($liste($b['peut_etre'])) ?></li>
<?php endif; ?>
<?php if ($b['non'] !== []): ?>
        <li><strong><?= $passee ? 'Ne pouvaient pas' : 'Ne peuvent pas' ?> :</strong> <?= e($liste($b['non'])) ?></li>
<?php endif; ?>
        <li><strong>Sans réponse :</strong> <?= e($b['sans_reponse'] === [] ? 'personne' : $liste($b['sans_reponse'])) ?></li>
      </ul>
<?php if ($passee): ?>
      <p class="repetition-carte__note">Patrice n’a pas encore noté qui était là.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($ligne['ouverte']): ?>
      <?= composant($c, 'presence', ['action' => url($config, '/repetitions/' . (int) $r['id'] . '/presence'), 'reponse' => $ligne['maReponse'], 'sujet' => 'la répétition du ' . $jour]) ?>
<?php endif; ?>
<?php if ($r['compte_rendu'] !== null): ?>
      <div class="repetition-carte__compte-rendu">
        <p class="repetition-carte__mot">Compte rendu</p>
        <p class="repetition-carte__texte"><?= e($r['compte_rendu']) ?></p>
      </div>
<?php elseif ($passee && $r['statut'] !== 'annulee'): ?>
      <p class="repetition-carte__note">Pas encore de compte rendu.</p>
<?php endif; ?>
    </article>
    <?php

    return (string) ob_get_clean();
};
$sondageWhatsapp = array_filter($avenir, static fn (array $l): bool => $l['repetition']['statut'] === 'proposee' && $l['repetition']['source'] === 'whatsapp') !== [];
?>
<div class="contenu repetitions">
  <h1 class="titre-ecran">Répétitions</h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>
<?php if (lien_sortant_valide($lienSondage)): ?>
  <a class="bouton bouton--or repetitions__sondage" href="<?= e((string) $lienSondage) ?>" rel="noopener noreferrer">Proposer des dates <span aria-hidden="true">↗</span><span class="visuellement-cache">(sondage, nouveau site)</span></a>
  <p class="repetitions__aide">Jessica et Stéphane ne sont pas sur WhatsApp : tout est aussi ici.</p>
<?php else: ?>
  <p class="encart encart--pointille"><?= $sondageWhatsapp
      ? 'Le sondage est dans le groupe WhatsApp ; sinon, dis tes dispos à Patrice.'
      : 'Pas de sondage en cours : dis tes dispos à Patrice.' ?></p>
<?php endif; ?>

  <section class="repetitions__partie" aria-labelledby="a-venir">
    <h2 class="ma-page__titre" id="a-venir">À venir</h2>
<?php if ($avenir === []): ?>
    <p>Pas de répétition prévue.</p>
<?php endif; ?>
<?php foreach ($avenir as $ligne): ?>
    <?= $carte($ligne, false) ?>
<?php endforeach; ?>
  </section>

  <section class="repetitions__partie" aria-labelledby="passees">
    <h2 class="ma-page__titre" id="passees">Passées</h2>
<?php if ($passees === []): ?>
    <p>Aucune répétition passée.</p>
<?php endif; ?>
<?php foreach ($passees as $ligne): ?>
    <?= $carte($ligne, true) ?>
<?php endforeach; ?>
  </section>
</div>
