<?php

declare(strict_types=1);

/** Administration de Patrice (phase 4) : invisible sans clé, sections validées, placements, fiche, réglages. */
final class AdminDonneesTest extends CasDonnees
{
    public function testToutLAdministrationRepond404SansLeCookie(): void
    {
        foreach (['/admin/reglages', '/admin/chansons', '/admin/chansons/kanimaa', '/admin/chansons/kanimaa/sections', '/admin/chansons/kanimaa/placements', '/admin/journal'] as $chemin) {
            self::assertSame(404, $this->obtenir($chemin, 'Patrice')->statut, $chemin);
            self::assertSame(200, $this->obtenir($chemin, 'Patrice', [], [], true)->statut, $chemin);
        }
        // Même avec le prénom « Patrice » et un jeton CSRF valable, un POST sans le cookie d'administration est un 404.
        self::assertSame(404, $this->envoyer('/admin/chansons/kanimaa/sections', 'Patrice', ['action' => 'ajouter', 'nom' => 'X', 'debut' => '0', 'fin' => '8', 'comptes' => '2'])->statut);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn());
    }

    public function testTableauMontreLesQuestionsOuvertesEtLEspace(): void
    {
        // Question posée par le test (aucune question du groupe n'est versionnée).
        Chores\poser_reglage($this->pdo, 'questions_ouvertes', json_encode(['Qui apporte l’enceinte ?'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $r = $this->obtenir('/admin', null, [], [], true);
        self::assertStringContainsString('Questions ouvertes du groupe', $r->corps);
        self::assertStringContainsString('Qui apporte l’enceinte ?', $r->corps);
        self::assertStringContainsString('Vidéos : 0 o sur 4,9 Go permis.', $r->corps);
    }

    public function testLireEtEcrireLesTemps(): void
    {
        self::assertSame(17.5, Chores\lire_temps('0:17,5'));
        self::assertSame(62.0, Chores\lire_temps('1:02'));
        self::assertSame(17.0, Chores\lire_temps('17'));
        self::assertSame(3725.3, Chores\lire_temps('1:02:05,3'));
        self::assertNull(Chores\lire_temps('0:75'));
        self::assertNull(Chores\lire_temps('dix'));
        self::assertNull(Chores\lire_temps('1:75:00'), 'minutes sous 60 avec des heures');
        self::assertNull(Chores\lire_temps(''));
        self::assertSame('0:17,5', Chores\format_temps_precis(17.5));
        self::assertSame('4:04,0', Chores\format_temps_precis(244));
    }

    public function testValidationDesSections(): void
    {
        $kanimaa = $this->chanson('kanimaa');
        $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $pdo = $this->pdo;
        $v = static fn (array $champs): array => Chores\valider_section($pdo, $kanimaa, $champs + ['nom' => 'Section B', 'debut' => '0:08', 'fin' => '0:16', 'comptes' => '2'])['erreurs'];

        self::assertSame([], $v([]));
        self::assertArrayHasKey('fin', $v(['fin' => '0:08']), 'fin = début');
        self::assertArrayHasKey('fin', $v(['fin' => '0:05']), 'fin avant le début');
        self::assertArrayHasKey('debut', $v(['debut' => '0:04']), 'chevauche Section A');
        self::assertStringContainsString('chevauche « Section A »', $v(['debut' => '0:04'])['debut']);
        self::assertArrayHasKey('fin', $v(['debut' => '4:00', 'fin' => '4:10']), 'après la durée (4:04)');
        self::assertArrayHasKey('comptes', $v(['comptes' => '0']));
        self::assertArrayHasKey('nom', $v(['nom' => str_repeat('x', 41)]));
        self::assertArrayHasKey('debut', $v(['debut' => 'midi']));
    }

    public function testLaBaseRefuseAussiUnChevauchement(): void
    {
        $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $this->expectException(PDOException::class);
        $this->ajouterSection('kanimaa', 'Section B', 7, 12);
    }

    public function testAjouterModifierOrdonnerSupprimerDesSections(): void
    {
        $url = '/admin/chansons/kanimaa/sections';
        $r = $this->envoyer($url, null, ['action' => 'ajouter', 'nom' => 'Section A', 'debut' => '0:00', 'fin' => '0:08', 'comptes' => '2'], [], true);
        self::assertSame(303, $r->statut);
        $this->envoyer($url, null, ['action' => 'ajouter', 'nom' => 'Section B', 'debut' => '0:08', 'fin' => '0:16,5', 'comptes' => '2', 'note' => 'Démo'], [], true);
        $sections = Chores\sections_de($this->pdo, (int) $this->chanson('kanimaa')['id']);
        self::assertSame(['Section A', 'Section B'], array_column($sections, 'nom'));
        self::assertSame(16.5, $sections[1]['fin']);

        $erreur = $this->envoyer($url, null, ['action' => 'ajouter', 'nom' => 'Section C', 'debut' => '0:10', 'fin' => '0:20', 'comptes' => '2'], [], true);
        self::assertSame(422, $erreur->statut);
        self::assertStringContainsString('chevauche « Section B »', $erreur->corps);
        self::assertStringContainsString('value="Section C"', $erreur->corps, 'aucune ressaisie');

        $this->envoyer($url, null, ['action' => 'descendre', 'id' => (string) $sections[0]['id']], [], true);
        self::assertSame(['Section B', 'Section A'], array_column(Chores\sections_de($this->pdo, (int) $this->chanson('kanimaa')['id']), 'nom'));
        $this->envoyer($url, null, ['action' => 'enregistrer', 'id' => (string) $sections[0]['id'], 'nom' => 'Section A bis', 'debut' => '0:00', 'fin' => '0:07', 'comptes' => '1'], [], true);
        self::assertSame(7.0, (float) $this->pdo->query('SELECT fin_s FROM sections WHERE id = ' . $sections[0]['id'])->fetchColumn());

        $confirmation = $this->obtenir($url, null, ['supprimer' => (string) $sections[1]['id']], [], true);
        self::assertStringContainsString('Supprimer « Section B » ?', $confirmation->corps);
        $sansConfirmer = $this->envoyer($url, null, ['action' => 'supprimer', 'id' => (string) $sections[1]['id']], [], true);
        self::assertSame(422, $sansConfirmer->statut);
        $this->envoyer($url, null, ['action' => 'supprimer', 'id' => (string) $sections[1]['id'], 'confirmer' => '1'], [], true);
        self::assertSame(['Section A bis'], array_column(Chores\sections_de($this->pdo, (int) $this->chanson('kanimaa')['id']), 'nom'));
        self::assertSame(1, (int) $this->pdo->query('SELECT ordre FROM sections')->fetchColumn());
    }

    public function testPlacementsEnAdministration(): void
    {
        $a = $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $b = $this->ajouterSection('kanimaa', 'Section B', 8, 16);
        $url = '/admin/chansons/kanimaa/placements';
        $alex = $this->idCousin('Alex');
        $jessica = $this->idCousin('Jessica');

        $r = $this->envoyer($url, null, ['section_id' => (string) $a, "x_{$alex}" => '10', "y_{$alex}" => '20', "x_{$jessica}" => '', "y_{$jessica}" => '', 'action' => 'enregistrer'], [], true);
        self::assertSame(303, $r->statut);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM placements WHERE section_id = {$a}")->fetchColumn());

        $this->envoyer($url, null, ['section_id' => (string) $a, "x_{$alex}" => '10', "y_{$alex}" => '20', 'fleche' => "{$jessica}:public"], [], true);
        self::assertSame([50, 55], array_map('intval', $this->pdo->query("SELECT x, y FROM placements WHERE section_id = {$a} AND cousin_id = {$jessica}")->fetch(PDO::FETCH_NUM)), 'un non placé part du centre');

        $erreur = $this->envoyer($url, null, ['section_id' => (string) $a, "x_{$alex}" => '120', "y_{$alex}" => '20'], [], true);
        self::assertSame(422, $erreur->statut);
        self::assertStringContainsString('Alex : x et y vont de 0 à 100', $erreur->corps);

        $copieVide = $this->envoyer($url, null, ['section_id' => (string) $a, 'action' => 'copier'], [], true);
        self::assertSame(422, $copieVide->statut, 'pas de section avant la première');
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM placements WHERE section_id = {$a}")->fetchColumn(), 'rien n’est effacé');
        $this->envoyer($url, null, ['section_id' => (string) $b, 'action' => 'copier'], [], true);
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM placements WHERE section_id = {$b}")->fetchColumn());
        self::assertSame(404, $this->envoyer($url, null, ['section_id' => '999'], [], true)->statut);
    }

    public function testFicheDUneChansonEtDanseurs(): void
    {
        $url = '/admin/chansons/thalapathy-kacheri';
        $champs = [
            'titre' => 'Thalapathy Kacheri', 'film' => 'Jana Nayagan (2026)', 'duree' => '3:17', 'bpm' => '142', 'statut' => 'proposee',
            'ordre_passage' => '', 'entree_qui' => '', 'entree_dou' => '', 'musique_qui' => '', 'duree_coupee' => '', 'note' => '',
            'danseur_' . $this->idCousin('Patrice') => 'danse', 'danseur_' . $this->idCousin('Alex') => 'danse',
        ];
        $r = $this->envoyer($url, null, $champs, [], true);
        self::assertSame(303, $r->statut);
        self::assertSame(['Alex', 'Patrice'], array_column(Chores\distribution($this->pdo, (int) $this->chanson('thalapathy-kacheri')['id']), 'prenom'));
        self::assertSame(0, (int) $this->chanson('thalapathy-kacheri')['bpm_verifie']);

        $erreur = $this->envoyer($url, null, ['bpm' => '500', 'duree' => 'trois minutes', 'statut' => 'inconnu'] + $champs, [], true);
        self::assertSame(422, $erreur->statut);
        self::assertStringContainsString('Tempo en temps par minute, de 40 à 240', $erreur->corps);
        self::assertStringContainsString('Durée illisible', $erreur->corps);

        $this->ajouterSection('thalapathy-kacheri', 'Section A', 0, 100);
        $court = $this->envoyer($url, null, ['duree' => '1:00'] + $champs, [], true);
        self::assertSame(422, $court->statut);
        self::assertStringContainsString('La section « Section A » finit après cette durée', $court->corps);
    }

    public function testUnDanseurRetireNeGardeNiPointNiRoleDeCreateur(): void
    {
        $a = $this->ajouterSection('yaaro-yaarodi', 'Section A', 0, 8);
        $b = $this->ajouterSection('yaaro-yaarodi', 'Section B', 8, 16);
        $jessica = $this->idCousin('Jessica');
        $this->pdo->prepare('INSERT INTO placements (section_id, cousin_id, x, y) VALUES (?, ?, 50, 50)')->execute([$a, $jessica]);
        $this->pdo->exec("UPDATE chansons SET createur_id = {$jessica} WHERE slug = 'yaaro-yaarodi'");

        $champs = ['titre' => 'Yaaro Yaarodi', 'statut' => 'proposee', 'a_creer' => '1', 'danseur_' . $this->idCousin('Josiane') => 'danse', 'danseur_' . $this->idCousin('Catherine') => 'danse'];
        self::assertSame(303, $this->envoyer('/admin/chansons/yaaro-yaarodi', null, $champs, [], true)->statut);

        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM placements WHERE cousin_id = {$jessica}")->fetchColumn());
        self::assertNull($this->chanson('yaaro-yaarodi')['createur_id']);
        self::assertSame(403, $this->envoyer("/placements/{$a}/moi", 'Jessica', ['direction' => 'droite'])->statut);
        // La copie ne ramène que les danseurs actuels.
        $this->pdo->prepare('INSERT INTO placements (section_id, cousin_id, x, y) VALUES (?, ?, 20, 20)')->execute([$a, $this->idCousin('Josiane')]);
        $this->envoyer('/admin/chansons/yaaro-yaarodi/placements', null, ['section_id' => (string) $b, 'action' => 'copier'], [], true);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM placements WHERE section_id = {$b}")->fetchColumn());
    }

    public function testLiensSortantsSeulementEnHttps(): void
    {
        $url = '/admin/chansons/kanimaa/liens';
        $refus = $this->envoyer($url, null, ['action' => 'ajouter', 'url' => 'javascript:alert(1)', 'titre_lien' => 'X', 'type' => 'tutoriel', 'miroir' => ''], [], true);
        self::assertSame(422, $refus->statut);
        self::assertSame(422, $this->envoyer($url, null, ['action' => 'ajouter', 'url' => 'https://exemple.test/long', 'titre_lien' => 'Long', 'duree_lien' => '11:00:00', 'type' => 'tutoriel', 'miroir' => ''], [], true)->statut, 'au-delà de 10 heures');
        $this->envoyer($url, null, ['action' => 'ajouter', 'url' => 'https://exemple.test/demo', 'titre_lien' => 'Démo', 'duree_lien' => '1:00', 'type' => 'tutoriel', 'miroir' => '1'], [], true);
        $id = (int) $this->pdo->query("SELECT id FROM liens WHERE url = 'https://exemple.test/demo'")->fetchColumn();
        self::assertGreaterThan(0, $id);
        $this->envoyer($url, null, ['action' => 'supprimer', 'lien' => (string) $id], [], true);
        self::assertFalse($this->pdo->query("SELECT id FROM liens WHERE url = 'https://exemple.test/demo'")->fetchColumn());
    }

    public function testReglages(): void
    {
        $champs = ['date_mariage' => '', 'lieu_mariage' => '', 'duree_danses_min' => '', 'lien_sondage' => 'http://pas-https.test', 'texte_entree' => '', 'contact_patrice' => 'sur WhatsApp'];
        $refus = $this->envoyer('/admin/reglages', null, $champs, [], true);
        self::assertSame(422, $refus->statut);
        self::assertStringContainsString('https://', $refus->corps);
        $dateFausse = $this->envoyer('/admin/reglages', null, ['lien_sondage' => '', 'date_mariage' => '2026-02-30'] + $champs, [], true);
        self::assertSame(422, $dateFausse->statut);

        self::assertSame(303, $this->envoyer('/admin/reglages', null, ['lien_sondage' => '', 'placements_verrouilles' => '1'] + $champs, [], true)->statut);
        self::assertSame('sur WhatsApp', Chores\reglage($this->pdo, 'contact_patrice'));
        self::assertSame('1', Chores\reglage($this->pdo, 'placements_verrouilles'));
        self::assertNull(Chores\reglage($this->pdo, 'date_mariage'));
    }

    public function testAjouterUneChanson(): void
    {
        $r = $this->envoyer('/admin/chansons', null, ['titre' => 'Démo'], [], true);
        self::assertSame(303, $r->statut);
        self::assertSame('/admin/chansons/demo?ok=ajoutee', $r->entetes['Location']);
        self::assertSame(422, $this->envoyer('/admin/chansons', null, ['titre' => 'Kanimaa'], [], true)->statut);
    }
}
