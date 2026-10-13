<?php

declare(strict_types=1);

/**
 * Répétitions (présences, constats, compte rendu), Jour J (ordre de passage, check-list), « Ce qui bouge » (phase 5).
 * Les répétitions créées ici sont datées de 2099 : les tests ne dépendent pas du jour où ils tournent.
 */
final class GroupeTest extends CasDonnees
{
    private function repetition(string $date, string $statut = 'decidee'): int
    {
        $this->pdo->prepare("INSERT INTO repetitions (date, statut, source, lieu) VALUES (?, ?, 'site', 'Salle Démo')")->execute([$date, $statut]);

        return (int) $this->pdo->lastInsertId();
    }

    private function journal(string $type): int
    {
        $requete = $this->pdo->prepare('SELECT COUNT(*) FROM journal WHERE type = ?');
        $requete->execute([$type]);

        return (int) $requete->fetchColumn();
    }

    public function testPresenceIdempotenteJournaliseeUneFois(): void
    {
        $id = $this->repetition('2099-03-07');
        $r = $this->envoyer("/repetitions/{$id}/presence", 'Jessica', ['reponse' => 'oui']);
        self::assertSame(303, $r->statut);
        self::assertSame("/repetitions?ok=oui&r={$id}#repetition-{$id}", $r->entetes['Location']);
        self::assertSame('oui', Chores\ma_reponse($this->pdo, $id, $this->idCousin('Jessica')));
        self::assertSame(1, $this->journal('presence'));

        // Même réponse renvoyée (double toucher, formulaire rechargé) : rien ne change, pas de nouvelle ligne.
        $this->envoyer("/repetitions/{$id}/presence", 'Jessica', ['reponse' => 'oui']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM presences WHERE repetition_id = {$id}")->fetchColumn());
        self::assertSame(1, $this->journal('presence'));

        $this->envoyer("/repetitions/{$id}/presence", 'Jessica', ['reponse' => 'non']);
        self::assertSame('non', Chores\ma_reponse($this->pdo, $id, $this->idCousin('Jessica')));
        self::assertSame(2, $this->journal('presence'));

        $page = $this->obtenir('/repetitions', 'Jessica', ['ok' => 'non', 'r' => (string) $id])->corps;
        self::assertStringContainsString('C’est noté : tu ne peux pas venir', $page);
        self::assertStringContainsString('Ta réponse : je ne peux pas.', $page);
        self::assertStringContainsString('Ne peuvent pas :</strong> Jessica', $page);

        self::assertSame(422, $this->envoyer("/repetitions/{$id}/presence", 'Jessica', ['reponse' => 'peut_etre'])->statut);
        self::assertSame(404, $this->envoyer('/repetitions/999999/presence', 'Jessica', ['reponse' => 'oui'])->statut);
    }

    public function testUneConfirmationNeSeForgePasParUnLien(): void
    {
        $id = $this->repetition('2099-03-09');
        $page = $this->obtenir('/moi', 'Catherine', ['ok' => 'oui', 'r' => (string) $id])->corps;
        self::assertStringNotContainsString('C’est noté', $page, 'Catherine n’a rien répondu');
        $this->envoyer("/repetitions/{$id}/presence", 'Catherine', ['reponse' => 'oui']);
        self::assertStringContainsString('C’est noté : tu viens', $this->obtenir('/moi', 'Catherine', ['ok' => 'oui', 'r' => (string) $id])->corps);
        self::assertStringNotContainsString('C’est noté', $this->obtenir('/moi', 'Catherine', ['ok' => 'non', 'r' => (string) $id])->corps);
    }

    public function testElision(): void
    {
        self::assertSame(['d’Alex', 'de Fabien', 'd’Énora', 'd’un cousin'], [Chores\de('Alex'), Chores\de('Fabien'), Chores\de('Énora'), Chores\de('un cousin')]);
    }

    public function testRetourSurMaPageEtVoteDuSondageRemplace(): void
    {
        $id = $this->repetition('2099-03-08', 'proposee');
        $this->pdo->prepare("INSERT INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES (?, ?, 'peut_etre', 'whatsapp', '2026-10-12T10:00:00Z')")
            ->execute([$id, $this->idCousin('Flobert')]);
        $r = $this->envoyer("/repetitions/{$id}/presence", 'Flobert', ['reponse' => 'oui', 'retour' => 'moi']);
        self::assertSame("/moi?ok=oui&r={$id}#prochaine-repetition", $r->entetes['Location']);
        $ligne = $this->pdo->query("SELECT reponse, source FROM presences WHERE repetition_id = {$id}")->fetch();
        self::assertSame(['oui', 'site'], [$ligne['reponse'], $ligne['source']]);
    }

    public function testRepetitionAnnuleeOuPasseeRefuseLesReponses(): void
    {
        $annulee = $this->repetition('2099-04-04', 'annulee');
        $r = $this->envoyer("/repetitions/{$annulee}/presence", 'Alex', ['reponse' => 'oui']);
        self::assertSame(409, $r->statut);
        self::assertStringContainsString('Cette répétition est annulée', $r->corps);

        $passee = $this->repetition('2020-01-04', 'decidee');
        self::assertSame(409, $this->envoyer("/repetitions/{$passee}/presence", 'Alex', ['reponse' => 'oui'])->statut);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM presences WHERE repetition_id IN ({$annulee}, {$passee})")->fetchColumn());

        $page = $this->obtenir('/repetitions', 'Alex')->corps;
        self::assertStringContainsString('Pas de répétition ce jour-là.', $page);
    }

    public function testPersonneNARepondu(): void
    {
        $this->repetition('2099-05-02');
        self::assertStringContainsString('Personne n’a encore répondu. Sois la première ou le premier.', $this->obtenir('/repetitions', 'Nadine')->corps);
    }

    public function testConstatsEtCompteRenduParPatrice(): void
    {
        $id = (int) $this->pdo->query("SELECT id FROM repetitions WHERE date = '2026-09-06'")->fetchColumn();
        // Réponse posée par le test (aucune réponse du groupe n'est versionnée).
        $this->pdo->exec("INSERT INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES ({$id}, {$this->idCousin('Alex')}, 'oui', 'whatsapp', '2026-09-05T21:27:00Z')");
        self::assertSame(404, $this->obtenir("/admin/repetitions/{$id}", 'Patrice')->statut, 'sans la clé : introuvable');
        $page = $this->obtenir("/admin/repetitions/{$id}", null, [], [], true);
        self::assertSame(200, $page->statut);
        self::assertStringContainsString('A dit « je viens » (WhatsApp).', $page->corps);

        $r = $this->envoyer("/admin/repetitions/{$id}", null, [
            'date' => '2026-09-06', 'heure' => 'midi – 19 h', 'lieu' => 'Villiers', 'statut' => 'passee', 'travail' => '', 'note' => '',
            'compte_rendu' => 'Compte rendu de test.',
            'constat' => [(string) $this->idCousin('Alex') => '1', (string) $this->idCousin('Fabien') => '0', (string) $this->idCousin('Nadine') => ''],
        ], [], true);
        self::assertSame(303, $r->statut);
        self::assertSame([$this->idCousin('Alex') => 1, $this->idCousin('Fabien') => 0], Chores\constats($this->pdo, $id));

        $page = $this->obtenir('/repetitions', 'Nadine')->corps;
        self::assertStringContainsString('Présents :</strong> Alex', $page);
        self::assertStringContainsString('Absents :</strong> Fabien', $page);
        self::assertStringContainsString('Compte rendu de test.', $page);

        $refus = $this->envoyer("/admin/repetitions/{$id}", null, ['date' => '2026-02-30', 'statut' => 'inconnu', 'constat' => [(string) $this->idCousin('Alex') => 'peut-être']], [], true);
        self::assertSame(422, $refus->statut);
        self::assertStringContainsString('Date illisible', $refus->corps);
        self::assertStringContainsString('Alex : choisis « pas noté », « présent » ou « absent ».', $refus->corps);
    }

    public function testAjoutDUneRepetitionEtDateEnDouble(): void
    {
        $champs = ['date' => '2099-06-06', 'heure' => '14 h', 'lieu' => 'Salle Démo', 'statut' => 'decidee', 'travail' => 'Section A', 'note' => ''];
        self::assertSame(303, $this->envoyer('/admin/repetitions', null, $champs, [], true)->statut);
        $double = $this->envoyer('/admin/repetitions', null, $champs, [], true);
        self::assertSame(422, $double->statut);
        self::assertStringContainsString('Une répétition existe déjà ce jour-là', $double->corps);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM repetitions WHERE date = '2099-06-06'")->fetchColumn());
        self::assertSame(404, $this->envoyer('/admin/repetitions', 'Patrice', $champs)->statut);
    }

    public function testJourJSansOrdrePuisAvecOrdre(): void
    {
        $page = $this->obtenir('/jour-j', 'Josiane')->corps;
        self::assertStringContainsString('Mariage le [date] · [lieu].', $page);
        self::assertStringContainsString('L’ordre de passage n’est pas encore fixé', $page);
        self::assertStringContainsString('Tenue', $page);

        $this->pdo->exec("UPDATE chansons SET ordre_passage = 1, duree_coupee_s = 150 WHERE slug = 'kanimaa'");
        $this->pdo->exec("UPDATE chansons SET ordre_passage = 2, entree_qui = 'Les huit', musique_qui = 'Nadine' WHERE slug = 'celebration-of-varisu'");
        Chores\poser_reglage($this->pdo, 'date_mariage', '2027-06-12');
        $page = $this->obtenir('/jour-j', 'Josiane')->corps;
        self::assertStringContainsString('Mariage le samedi 12 juin 2027', $page);
        self::assertLessThan(strpos($page, 'Celebration of Varisu'), strpos($page, 'Kanimaa'));
        self::assertStringContainsString('2 min 30', $page);
        self::assertStringContainsString('2 min 48 (chanson entière)', $page);
        self::assertStringContainsString('<strong>Qui entre :</strong> Les huit', $page);
        self::assertStringContainsString('[à compléter]', $page);
        self::assertStringContainsString('/placements/kanimaa?scene=1&amp;depuis=jour-j#scene', $page);

        $scene = $this->obtenir('/placements/kanimaa', 'Josiane', ['scene' => '1', 'depuis' => 'jour-j'])->corps;
        self::assertMatchesRegularExpression('#href="/jour-j">Quitter#', $scene);
    }

    public function testCheckListParEtatVoulu(): void
    {
        $id = (int) $this->pdo->query("SELECT id FROM checklist WHERE libelle = 'Tenue'")->fetchColumn();
        $r = $this->envoyer("/jour-j/check/{$id}", 'Josiane', ['fait' => '1']);
        self::assertSame(303, $r->statut);
        // Deux cousins touchent « fait » en même temps : la ligne reste faite (pas de bascule qui s'annule).
        $this->envoyer("/jour-j/check/{$id}", 'Alex', ['fait' => '1']);
        $ligne = Chores\ligne_checklist($this->pdo, (string) $id);
        self::assertSame([1, $this->idCousin('Josiane')], [(int) $ligne['fait'], (int) $ligne['fait_par']]);
        self::assertSame(1, $this->journal('check'));
        self::assertStringContainsString('fait par Josiane', $this->obtenir('/jour-j', 'Alex')->corps);

        $this->envoyer("/jour-j/check/{$id}", 'Alex', ['fait' => '0']);
        $ligne = Chores\ligne_checklist($this->pdo, (string) $id);
        self::assertSame([0, null], [(int) $ligne['fait'], $ligne['fait_par']]);
        self::assertSame(422, $this->envoyer("/jour-j/check/{$id}", 'Alex', ['fait' => 'peut-être'])->statut);
    }

    public function testAdministrationDeLaCheckList(): void
    {
        self::assertSame(303, $this->envoyer('/admin/jour-j', null, ['action' => 'ajouter', 'libelle' => 'Ligne Démo'], [], true)->statut);
        self::assertSame(422, $this->envoyer('/admin/jour-j', null, ['action' => 'ajouter', 'libelle' => str_repeat('x', 81)], [], true)->statut);
        $lignes = Chores\checklist($this->pdo);
        self::assertSame('Ligne Démo', end($lignes)['libelle']);
        $demo = end($lignes)['id'];

        $this->envoyer('/admin/jour-j', null, ['action' => 'monter', 'id' => (string) $demo], [], true);
        self::assertSame('Ligne Démo', Chores\checklist($this->pdo)[count($lignes) - 2]['libelle']);
        $this->envoyer('/admin/jour-j', null, ['action' => 'renommer', 'id' => (string) $demo, 'libelle' => 'Ligne Démo 2'], [], true);
        self::assertContains('Ligne Démo 2', array_column(Chores\checklist($this->pdo), 'libelle'));
        self::assertStringContainsString('Supprimer la ligne « Ligne Démo 2 » ?', $this->obtenir('/admin/jour-j', null, ['supprimer' => (string) $demo], [], true)->corps);
        $this->envoyer('/admin/jour-j', null, ['action' => 'supprimer', 'id' => (string) $demo], [], true);
        self::assertNotContains('Ligne Démo 2', array_column(Chores\checklist($this->pdo), 'libelle'));
    }

    public function testCeQuiBougeMontreLesPresences(): void
    {
        $id = $this->repetition('2099-07-04');
        $this->envoyer("/repetitions/{$id}/presence", 'Stéphane', ['reponse' => 'oui']);
        $fil = Chores\ce_qui_bouge($this->pdo, 10, '2099-01-01');
        self::assertSame('Stéphane vient samedi 4 juillet', $fil[0]['titre']);
        self::assertSame('/repetitions', $fil[0]['chemin']);
        self::assertStringContainsString('Stéphane vient', $this->obtenir('/moi', 'Alex')->corps);
    }
}
