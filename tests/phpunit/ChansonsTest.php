<?php

declare(strict_types=1);

/** Ma page, page d'une chanson, niveaux, « qui s'en occupe », placements, répétitions, état JSON (phase 4). */
final class ChansonsTest extends CasDonnees
{
    public function testJessicaVoitSesTroisChoresEtLesProposees(): void
    {
        $r = $this->obtenir('/moi', 'Jessica');

        self::assertSame(200, $r->statut);
        self::assertStringContainsString('Salut Jessica', $r->corps);
        preg_match_all('#<h3 class="carte-chanson__titre">([^<]+)</h3>#', $r->corps, $m);
        self::assertSame(['Celebration of Varisu', 'Kanimaa', 'Yaaro Yaarodi'], $m[1]);
        self::assertStringContainsString('Proposées, pas tranchées', $r->corps);
        self::assertStringContainsString('Maamadura, Sangeeth Celebration, The Wedding Song', $r->corps);
        self::assertStringContainsString('Choré à créer', $r->corps);
        self::assertStringContainsString('Les sections ne sont pas encore posées.', $r->corps);
        foreach (['/placements', '/repetitions', '/videos', '/jour-j'] as $lien) {
            self::assertStringContainsString('href="' . $lien . '"', $r->corps);
        }
        self::assertStringNotContainsString('Questions ouvertes', $r->corps, 'pense-bête de Patrice seulement');
    }

    public function testNadineUnSoloDecideACreer(): void
    {
        $r = $this->obtenir('/moi', 'Nadine');
        preg_match_all('#<h3 class="carte-chanson__titre">([^<]+)</h3>#', $r->corps, $m);
        self::assertSame(['Innisai'], $m[1]);
        self::assertStringContainsString('Choré décidée par le groupe', $r->corps);
        self::assertStringContainsString('solo de Nadine', $r->corps);

        $page = $this->obtenir('/chanson/innisai', 'Nadine');
        self::assertStringContainsString('[film à confirmer]', $page->corps);
        self::assertStringContainsString('[durée inconnue]', $page->corps);
        self::assertStringContainsString('tempo à confirmer (97 tpm ?)', $page->corps);
        self::assertStringContainsString('Qui s’en occupe ?', $page->corps);
        self::assertStringContainsString('Nadine coupe la musique.', $page->corps);
        self::assertStringNotContainsString('Ma place', $page->corps, 'solo : pas de placements');
    }

    public function testPatriceEnAttenteSurKacheri(): void
    {
        $r = $this->obtenir('/moi', 'Patrice');
        self::assertStringContainsString('En attente — tu as demandé à t’ajouter', $r->corps);

        $page = $this->obtenir('/chanson/thalapathy-kacheri', 'Patrice');
        self::assertStringContainsString('Alex, Fabien, Flobert · Patrice en attente', $page->corps);
        self::assertStringContainsString('En attente : tu as demandé à rejoindre cette choré', $page->corps);
        self::assertStringContainsString('Mahir Shanz, vue miroir, pas à pas (malayalam)', $page->corps);
        self::assertStringContainsString('rel="noopener noreferrer"', $page->corps);
    }

    public function testStephaneSansLienWhatsAppEtUnCousinSansChore(): void
    {
        $this->pdo->exec("DELETE FROM distributions WHERE cousin_id = (SELECT id FROM cousins WHERE prenom = 'Stéphane')");
        $r = $this->obtenir('/moi', 'Stéphane');
        self::assertStringContainsString('Tu n’es sur aucune choré pour l’instant.', $r->corps);
    }

    public function testKanimaaSansSectionNiVideo(): void
    {
        $r = $this->obtenir('/chanson/kanimaa', 'Jessica');

        self::assertSame(200, $r->statut);
        self::assertStringContainsString('<title>Kanimaa — Chorés du mariage</title>', $r->corps);
        self::assertStringContainsString('Les sections ne sont pas encore posées. Patrice peut les créer dans l’administration.', $r->corps);
        self::assertStringContainsString('Pas encore de vidéo de référence pour cette chanson.', $r->corps);
        self::assertStringContainsString('Retro (2025) · 4 min 04 · tempo à confirmer (145 tpm ?) · 8 danseurs', $r->corps);
        self::assertStringContainsString('Reprise dansée d’Ahinth Vijay ft. The Officials', $r->corps);
        self::assertStringContainsString('référence partagée dans le groupe', $r->corps);
        self::assertStringContainsString('Personne n’a encore posté pour cette chanson.', $r->corps);
        self::assertSame(404, $this->obtenir('/chanson/inconnue', 'Jessica')->statut);
        self::assertSame(302, $this->obtenir('/chanson/kanimaa', null)->statut, 'sans prénom');
    }

    public function testVarisuTempoVerifie(): void
    {
        $r = $this->obtenir('/chanson/celebration-of-varisu', 'Alex');
        self::assertStringContainsString('Varisu (2023) · 2 min 48 · 140 tpm · 8 danseurs', $r->corps);
        self::assertStringContainsString('data-bpm="140"', $r->corps);
    }

    public function testNiveauxGrilleEtProgressionSansJavaScript(): void
    {
        $a = $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $this->ajouterSection('kanimaa', 'Section B', 8, 16);

        $r = $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Jessica', ['niveau' => '2']);
        self::assertSame(303, $r->statut);
        self::assertSame("/chanson/kanimaa?section=1#section-{$a}", $r->entetes['Location']);

        $page = $this->obtenir('/chanson/kanimaa', 'Jessica', ['section' => '1']);
        self::assertStringContainsString('Toi : 1 section sur 2 maîtrisée.', $page->corps);
        self::assertMatchesRegularExpression('#<tr class="grille-maitrise__moi">\s*<th scope="row">Jessica \(toi\)</th>\s*<td class="grille-maitrise__case grille-maitrise__case--2">#', $page->corps);
        self::assertStringContainsString('1 section sur 2 maîtrisée', $this->obtenir('/moi', 'Jessica')->corps);
        self::assertStringContainsString('Jessica maîtrise Section A', $this->obtenir('/moi', 'Alex')->corps, 'Ce qui bouge');
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'niveau'")->fetchColumn());

        // Reposer le même niveau ne change ni la date ni « Ce qui bouge ».
        $this->pdo->exec("UPDATE niveaux SET maj_le = '2026-01-01T00:00:00Z'");
        $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Jessica', ['niveau' => '2']);
        self::assertSame('2026-01-01T00:00:00Z', $this->pdo->query('SELECT maj_le FROM niveaux')->fetchColumn());
    }

    public function testNiveauEnJsonEtBornes(): void
    {
        $a = $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $json = ['accept' => 'application/json'];

        $r = $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Josiane', ['niveau' => '1'], $json);
        self::assertSame(200, $r->statut);
        self::assertSame('application/json; charset=utf-8', $r->entetes['Content-Type']);
        self::assertSame(['section' => $a, 'niveau' => 1, 'libelle' => 'Presque', 'maitrisees' => 0, 'total' => 1, 'progression' => 'rien de marqué encore'], json_decode($r->corps, true));

        self::assertSame(422, $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Josiane', ['niveau' => '3'], $json)->statut);
        self::assertSame(422, $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Josiane', ['niveau' => '-1'])->statut);
        self::assertSame(403, $this->envoyer("/chanson/kanimaa/section/{$a}/niveau", 'Nadine', ['niveau' => '2'], $json)->statut, 'Nadine ne danse pas Kanimaa');
        self::assertSame(404, $this->envoyer('/chanson/kanimaa/section/999/niveau', 'Josiane', ['niveau' => '2'])->statut);
        self::assertSame(404, $this->envoyer("/chanson/yaaro-yaarodi/section/{$a}/niveau", 'Josiane', ['niveau' => '2'])->statut, 'section d’une autre chanson');
        // Patrice, en attente sur Kacheri, marque aussi ses niveaux.
        $k = $this->ajouterSection('thalapathy-kacheri', 'Section A', 0, 8);
        self::assertSame(303, $this->envoyer("/chanson/thalapathy-kacheri/section/{$k}/niveau", 'Patrice', ['niveau' => '2'])->statut);
        self::assertSame(1, (int) $this->pdo->query("SELECT niveau FROM niveaux WHERE section_id = {$a}")->fetchColumn());
    }

    public function testQuiSOccupeDUneChoreACreer(): void
    {
        $r = $this->envoyer('/chanson/yaaro-yaarodi/createur', 'Josiane', ['createur' => 'Catherine']);
        self::assertSame(303, $r->statut);
        self::assertSame($this->idCousin('Catherine'), (int) $this->chanson('yaaro-yaarodi')['createur_id']);
        self::assertStringContainsString('Catherine s’en occupe', $this->obtenir('/moi', 'Jessica')->corps);
        self::assertSame('createur', $this->pdo->query("SELECT type FROM journal WHERE type = 'createur'")->fetchColumn());

        self::assertSame(403, $this->envoyer('/chanson/yaaro-yaarodi/createur', 'Alex', ['createur' => 'Catherine'])->statut, 'Alex ne danse pas Yaaro');
        self::assertSame(422, $this->envoyer('/chanson/yaaro-yaarodi/createur', 'Josiane', ['createur' => 'Alex'])->statut);
        self::assertSame(404, $this->envoyer('/chanson/kanimaa/createur', 'Josiane', ['createur' => 'Josiane'])->statut, 'pas à créer');
    }

    public function testModeRepetitionEtEtatJson(): void
    {
        $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $this->ajouterSection('kanimaa', 'Section B', 8, 16, 3);
        $r = $this->obtenir('/chanson/kanimaa/repeter', 'Alex', ['section' => '2']);
        self::assertSame(200, $r->statut);
        self::assertStringContainsString('data-theme="sombre"', $r->corps);
        self::assertStringContainsString('data-courante="1"', $r->corps);
        self::assertStringContainsString('href="/chanson/kanimaa?section=2#lecteur-chanson"', $r->corps);

        $json = json_decode($this->obtenir('/api/etat', 'Alex', ['chanson' => 'kanimaa'])->corps, true);
        self::assertSame('kanimaa', $json['chanson']);
        self::assertSame(1, $json['version']);
        self::assertSame(['Section A', 'Section B'], array_column($json['sections'], 'nom'));
        self::assertSame([0, 0], array_column($json['sections'], 'niveau'));
        self::assertSame(404, $this->obtenir('/api/etat', 'Alex', ['chanson' => 'rien'])->statut);
    }

    public function testPlacementsDeplacementVuParTousEtVerrou(): void
    {
        $a = $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $this->pdo->prepare('INSERT INTO placements (section_id, cousin_id, x, y) VALUES (?, ?, 50, 50)')->execute([$a, $this->idCousin('Fabien')]);

        self::assertSame('/placements/celebration-of-varisu', $this->obtenir('/placements', 'Fabien')->entetes['Location']);
        $avant = $this->obtenir('/placements/kanimaa', 'Fabien');
        self::assertStringContainsString('Déplacer mon point (Fabien)', $avant->corps);
        self::assertStringContainsString('Non placés :', $avant->corps);

        $r = $this->envoyer("/placements/{$a}/moi", 'Fabien', ['direction' => 'droite']);
        self::assertSame(303, $r->statut);
        self::assertSame([55, 50], array_map('intval', $this->pdo->query("SELECT x, y FROM placements WHERE section_id = {$a}")->fetch(PDO::FETCH_NUM)));
        self::assertStringContainsString('<strong>Fabien :</strong> au centre, au milieu', $this->obtenir('/placements/kanimaa', 'Josiane')->corps);
        self::assertSame(409, $this->envoyer("/placements/{$a}/moi", 'Josiane', ['direction' => 'droite'])->statut, 'Josiane pas encore placée');
        self::assertSame(422, $this->envoyer("/placements/{$a}/moi", 'Fabien', ['direction' => 'haut'])->statut);

        for ($i = 0; $i < 30; ++$i) {
            $this->envoyer("/placements/{$a}/moi", 'Fabien', ['direction' => 'fond']);
        }
        self::assertSame(0, (int) $this->pdo->query("SELECT y FROM placements WHERE section_id = {$a}")->fetchColumn(), 'borné à la scène');

        Chores\poser_reglage($this->pdo, 'placements_verrouilles', '1');
        self::assertSame(403, $this->envoyer("/placements/{$a}/moi", 'Fabien', ['direction' => 'droite'])->statut);
        self::assertStringContainsString('Les placements sont fixés', $this->obtenir('/placements/kanimaa', 'Fabien')->corps);
        self::assertSame(404, $this->obtenir('/placements/innisai', 'Fabien')->statut, 'solo : pas de placements');
    }

    public function testModeScene(): void
    {
        $this->ajouterSection('kanimaa', 'Section A', 0, 8);
        $r = $this->obtenir('/placements/kanimaa', 'Alex', ['scene' => '1']);
        self::assertStringContainsString('Mode scène', $r->corps);
        self::assertStringContainsString('scene--grand', $r->corps);
        self::assertStringContainsString('<input type="hidden" name="scene" value="1">', $r->corps);
    }

    public function testProchaineRepetition(): void
    {
        // Jeu d'essai posé par le test (aucune date proposée n'est versionnée) : deux options, deux dispos sur la seconde.
        $this->pdo->exec("INSERT INTO repetitions (date, statut, source) VALUES ('2026-10-16', 'proposee', 'whatsapp'), ('2026-10-17', 'proposee', 'whatsapp')");
        $id17 = (int) $this->pdo->query("SELECT id FROM repetitions WHERE date = '2026-10-17'")->fetchColumn();
        foreach (['Catherine', 'Stéphane'] as $prenom) {
            $this->pdo->exec("INSERT INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES ({$id17}, {$this->idCousin($prenom)}, 'peut_etre', 'whatsapp', '2026-10-12T21:31:00Z')");
        }
        $p = Chores\prochaine_repetition($this->pdo, '2026-10-13');
        self::assertFalse($p['fixee']);
        self::assertSame('2026-10-17', $p['repetition']['date']);
        self::assertSame(2, $p['dispos']);
        self::assertNull(Chores\prochaine_repetition($this->pdo, '2026-10-18'));
        $this->pdo->exec("UPDATE repetitions SET statut = 'decidee' WHERE date = '2026-10-16'");
        $p = Chores\prochaine_repetition($this->pdo, '2026-10-13');
        self::assertTrue($p['fixee']);
        self::assertSame('2026-10-16', $p['repetition']['date']);
        self::assertSame('samedi 17 octobre', Chores\date_en_mots('2026-10-17', '2026-10-13'));
        self::assertSame('dimanche 6 septembre 2026', Chores\date_en_mots('2026-09-06', '2027-01-01'));
    }

    public function testRepetitionsEtPagesDuGroupe(): void
    {
        // Réponses posées par le test (aucune réponse du groupe n'est versionnée).
        $id = (int) $this->pdo->query("SELECT id FROM repetitions WHERE date = '2026-09-06'")->fetchColumn();
        foreach (['Catherine', 'Jessica'] as $prenom) {
            $this->pdo->exec("INSERT INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES ({$id}, {$this->idCousin($prenom)}, 'oui', 'whatsapp', '2026-09-05T21:27:00Z')");
        }
        $r = $this->obtenir('/repetitions', 'Flobert');
        self::assertSame(200, $r->statut);
        self::assertStringContainsString('Villiers', $r->corps);
        self::assertStringContainsString('Avaient dit venir :</strong> Catherine, Jessica', $r->corps);
        // Selon la date du jour, les options du sondage WhatsApp sont encore à venir ou déjà passées.
        self::assertMatchesRegularExpression('/Le sondage est dans le groupe WhatsApp ; sinon, dis tes dispos à Patrice\.|Pas de sondage en cours : dis tes dispos à Patrice\./u', $r->corps);
        self::assertStringContainsString('Ces vidéos restent entre nous', $this->obtenir('/videos', 'Flobert')->corps);
        self::assertStringContainsString('L’ordre de passage n’est pas encore fixé', $this->obtenir('/jour-j', 'Flobert')->corps);
    }

    public function testPiedDePageAvecLeContactDesReglages(): void
    {
        self::assertStringContainsString('Écris à Patrice [contact]', $this->obtenir('/moi', 'Alex')->corps);
        Chores\poser_reglage($this->pdo, 'contact_patrice', 'sur WhatsApp');
        self::assertStringContainsString('Écris à Patrice sur WhatsApp', $this->obtenir('/moi', 'Alex')->corps);
    }
}
