<?php

declare(strict_types=1);

use function Chores\composant;
use function Chores\date_en_mots;
use function Chores\e;
use function Chores\infos_chanson;
use function Chores\repetition_ouverte;
use function Chores\texte_danseurs;
use function Chores\url;

/**
 * Ma page (maquette 1b, 1c, 1d, 1y) : salut, prochaine répétition avec « Je viens / Je ne peux pas », mes chorés,
 * chansons proposées sans danseur, « Ce qui bouge » (vidéos, commentaires, présences, niveaux), navigation.
 * @var Chores\Contexte $c
 * @var array<string, mixed> $config
 * @var list<array{chanson: array<string, mixed>, distribution: list<array{cousin_id: int, prenom: string, statut: string}>, maitrisees: int, total: int}> $chores
 * @var ?array{repetition: array<string, mixed>, fixee: bool, dispos: int, viennent: list<string>, nePeuventPas: list<string>} $prochaine
 * @var ?string $maReponse
 * @var ?string $confirmation
 * @var string $aujourdhui
 * @var ?string $lienSondage
 * @var list<array<string, mixed>> $proposees
 * @var list<array{vignette: string, titre: string, detail: string, chemin: string}> $fil
 */
$prenom = (string) $c->cousin['prenom'];
$carte = null;
if ($prochaine !== null) {
    $r = $prochaine['repetition'];
    $jour = date_en_mots((string) $r['date'], $aujourdhui);
    $quand = $jour . ($r['heure'] !== null ? ' · ' . $r['heure'] : '');
    $carte = $prochaine['fixee']
        ? ['quand' => $quand, 'lieu' => $r['lieu'] ?? '[lieu à préciser]', 'travail' => $r['travail'], 'sujet' => 'la répétition du ' . $quand]
        : [
            'piste' => $prochaine['dispos'] > 0
                ? sprintf('En tête du sondage : %s (%d dispo%s).', $jour, $prochaine['dispos'], $prochaine['dispos'] > 1 ? 's' : '')
                : sprintf('Date proposée : %s (personne n’a encore dit être dispo).', $jour),
            'sujet' => 'la date proposée du ' . $jour,
        ]
        // Le sondage du moment est dans le groupe WhatsApp (pas d'adresse) ; Jessica et Stéphane n'y sont pas.
        + ($r['source'] === 'whatsapp' ? ['repli' => 'Le sondage est dans le groupe WhatsApp ; sinon, dis tes dispos à Patrice.'] : []);
    $carte += ['viennent' => $prochaine['viennent'], 'nePeuventPas' => $prochaine['nePeuventPas'], 'maReponse' => $maReponse];
    if (repetition_ouverte($r, $aujourdhui)) {
        $carte += ['action' => url($config, '/repetitions/' . (int) $r['id'] . '/presence'), 'retour' => 'moi'];
    }
}
?>
<div class="contenu ma-page">
  <h1 class="salut">Salut <?= e($prenom) ?></h1>
<?php if ($confirmation !== null): ?>
  <?= composant($c, 'confirmation', ['texte' => $confirmation]) ?>
<?php endif; ?>

<?php if ($carte !== null): ?>
  <?= composant($c, 'carte-repetition', $carte + ['id' => 'prochaine-repetition', 'lienSondage' => $lienSondage, 'hrefRepetitions' => url($config, '/repetitions')]) ?>
<?php else: ?>
  <section class="carte-repetition" aria-labelledby="prochaine-repetition">
    <h2 class="carte-repetition__etiquette" id="prochaine-repetition">Prochaine répétition</h2>
    <p class="carte-repetition__lieu">Pas de répétition prévue.</p>
    <a class="carte-repetition__sondage" href="<?= e(url($config, '/repetitions')) ?>">Voir les répétitions</a>
  </section>
<?php endif; ?>

  <section class="ma-page__partie" aria-labelledby="tes-chores">
    <h2 class="ma-page__titre" id="tes-chores">Tes chorés</h2>
<?php if ($chores === []): ?>
    <p>Tu n’es sur aucune choré pour l’instant.</p>
<?php endif; ?>
<?php foreach ($chores as $ligne): ?>
<?php
    $ch = $ligne['chanson'];
    $infos = infos_chanson($ch);
    $danseurs = array_values(array_filter($ligne['distribution'], static fn (array $d): bool => $d['statut'] === 'danse'));
    $createur = null;
    if ((int) $ch['a_creer'] === 1) {
        $nomCreateur = null;
        foreach ($ligne['distribution'] as $d) {
            if ($d['cousin_id'] === (int) $ch['createur_id']) {
                $nomCreateur = $d['prenom'];
            }
        }
        $createur = $nomCreateur !== null ? $nomCreateur . ' s’en occupe' : 'qui s’en occupe ? ' . implode(', ', array_column($danseurs, 'prenom'));
    }
?>
    <?= composant($c, 'carte-chanson', [
        'titre' => (string) $ch['titre'],
        'duree' => $infos['duree'],
        'film' => $infos['film'],
        'danseurs' => texte_danseurs($ligne['distribution']),
        'createurs' => $createur,
        'decidee' => $ch['statut'] === 'decidee' ? 'Choré décidée par le groupe' : null,
        'attente' => $ch['ma_place'] === 'en_attente' ? 'tu as demandé à t’ajouter, pas encore de réponse. Tu peux déjà répéter.' : null,
        'maitrisees' => $ligne['maitrisees'],
        'total' => $ligne['total'],
        'href' => url($config, '/chanson/' . $ch['slug']),
    ]) ?>
<?php endforeach; ?>
<?php if ($proposees !== []): ?>
    <?= composant($c, 'encart', [
        'variante' => 'pointille',
        'titre' => 'Proposées, pas tranchées',
        'texte' => implode(', ', array_column($proposees, 'titre')),
        'note' => 'Personne n’a décidé : on garde ou on laisse tomber ? À régler à la prochaine répétition.',
    ]) ?>
<?php endif; ?>
  </section>

  <section class="ma-page__partie" aria-labelledby="ce-qui-bouge">
    <h2 class="ma-page__titre" id="ce-qui-bouge">Ce qui bouge</h2>
    <?= composant($c, 'fil', [
        'elements' => array_map(static fn (array $f): array => [
            'vignette' => $f['vignette'],
            'titre' => $f['titre'],
            'detail' => $f['detail'],
            'href' => url($config, $f['chemin']),
        ], $fil),
        'vide' => 'Rien de neuf pour l’instant : vidéos, commentaires, réponses aux répétitions et sections maîtrisées s’afficheront ici.',
    ]) ?>
  </section>

  <section class="ma-page__partie" aria-labelledby="gardees-titre" data-gardees-section hidden>
    <h2 class="ma-page__titre" id="gardees-titre">Gardées sur ton téléphone</h2>
    <ul class="hors-ligne__liste" data-liste-gardees hidden></ul>
  </section>

  <nav class="ma-page__navigation" aria-label="Aller à">
    <a class="bouton bouton--contour ma-page__lien" href="<?= e(url($config, '/placements')) ?>">Placements</a>
    <a class="bouton bouton--contour ma-page__lien" href="<?= e(url($config, '/repetitions')) ?>">Répétitions</a>
    <a class="bouton bouton--contour ma-page__lien" href="<?= e(url($config, '/videos')) ?>">Vidéos des cousins</a>
    <a class="bouton bouton--contour ma-page__lien" href="<?= e(url($config, '/jour-j')) ?>">Jour J</a>
  </nav>
</div>
