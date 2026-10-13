<?php

declare(strict_types=1);

/** Parcours d'accès traité en mémoire, sans serveur : lien, prénom, Ma page, administration, renouvellement. */
final class ParcoursTest extends CasDeBase
{
    private string $jeton;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jeton = Chores\nouveau_jeton($this->pdo);
    }

    public function testSansCookieOnEstEnvoyeVersLePageDuLien(): void
    {
        foreach (['/', '/moi'] as $chemin) {
            $r = $this->traiter($this->contexte('GET', $chemin));
            self::assertSame(302, $r->statut, $chemin);
            self::assertSame('/lien', $r->entetes['Location']);
        }
        self::assertSame(200, $this->traiter($this->contexte('GET', '/lien'))->statut);
    }

    public function testUnFauxLienMeneALaPageDuLienEtSeJournalise(): void
    {
        $r = $this->traiter($this->contexte('GET', '/e/' . str_repeat('A', 43)));

        self::assertSame(303, $r->statut);
        self::assertSame('/lien', $r->entetes['Location']);
        self::assertArrayNotHasKey('cm_acces', $this->cookiesPoses($r));
        self::assertSame('refus_jeton', $this->pdo->query('SELECT type FROM journal')->fetchColumn());
    }

    public function testLeBonLienPoseLeCookieEtRetireLeJetonDeLAdresse(): void
    {
        $r = $this->traiter($this->contexte('GET', '/chores/e/' . $this->jeton, [], [], '/chores'));

        self::assertSame(303, $r->statut);
        self::assertSame('/chores/', $r->entetes['Location']);
        self::assertSame('no-store', $r->entetes['Cache-Control']);
        $acces = $this->cookiesPoses($r)['cm_acces'];
        self::assertTrue(Chores\cookie_acces_valide(self::SECRET, 1, $acces));
        $journal = (string) json_encode($this->pdo->query('SELECT * FROM journal')->fetchAll());
        self::assertStringNotContainsString($this->jeton, $journal);
        self::assertStringContainsString('entree', $journal);
    }

    public function testAvecLeLienOnVoitLesNeufPrenomsPuisMaPage(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $entree = $this->traiter($this->contexte('GET', '/', ['cm_acces' => $acces]));
        self::assertSame(200, $entree->statut);
        foreach (['Alex', 'Catherine', 'Fabien', 'Flobert', 'Jessica', 'Josiane', 'Nadine', 'Patrice', 'Stéphane'] as $prenom) {
            self::assertStringContainsString('>' . $prenom . '</button>', $entree->corps);
        }

        $prenom = Chores\valeur_cookie_prenom(self::SECRET, 5, 1);
        $moi = $this->traiter($this->contexte('GET', '/moi', ['cm_acces' => $acces, 'cm_prenom' => $prenom]));
        self::assertSame(200, $moi->statut);
        self::assertStringContainsString('Salut Jessica', $moi->corps);
        self::assertStringContainsString('Changer', $moi->corps);

        $retour = $this->traiter($this->contexte('GET', '/', ['cm_acces' => $acces, 'cm_prenom' => $prenom]));
        self::assertSame(302, $retour->statut);
        self::assertSame('/moi', $retour->entetes['Location']);
    }

    public function testSansPrenomMaPageRenvoieALEntree(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $r = $this->traiter($this->contexte('GET', '/moi', ['cm_acces' => $acces, 'cm_prenom' => '5.faux']));

        self::assertSame(302, $r->statut);
        self::assertSame('/', $r->entetes['Location']);
    }

    public function testChangerEffaceLePrenom(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $csrf = Chores\base64url(random_bytes(32));
        $r = $this->traiter($this->contexte(
            'POST',
            '/changer',
            ['cm_acces' => $acces, 'cm_prenom' => Chores\valeur_cookie_prenom(self::SECRET, 5, 1), 'cm_csrf' => $csrf],
            ['csrf' => Chores\champ_csrf_attendu(self::SECRET, $csrf)]
        ));

        self::assertSame(303, $r->statut);
        self::assertSame('/', $r->entetes['Location']);
        self::assertSame('', $this->cookiesPoses($r)['cm_prenom']);
    }

    public function testUnPrenomInconnuNEstPasPose(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $csrf = Chores\base64url(random_bytes(32));
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $acces, 'cm_csrf' => $csrf],
            ['cousin' => '42', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, $csrf)]
        ));

        self::assertSame(303, $r->statut);
        self::assertSame('/', $r->entetes['Location']);
        self::assertArrayNotHasKey('cm_prenom', $this->cookiesPoses($r));
    }

    public function testAdministrationEn404SansCookie(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        foreach (['/admin', '/admin/lien'] as $chemin) {
            self::assertSame(404, $this->traiter($this->contexte('GET', $chemin))->statut, $chemin);
            self::assertSame(404, $this->traiter($this->contexte('GET', $chemin, ['cm_acces' => $acces]))->statut, $chemin);
            self::assertSame(404, $this->traiter($this->contexte('GET', $chemin, ['cm_admin' => Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN)]))->statut, $chemin . ' sans lien');
        }
        self::assertSame(404, $this->traiter($this->contexte('GET', '/admin/cle/mauvaise-cle'))->statut);
        self::assertSame(404, $this->traiter($this->contexte('POST', '/admin/lien', ['cm_acces' => $acces]))->statut);
        // Une méthode refusée ne trahit pas l'existence d'une route d'administration.
        self::assertSame(404, $this->traiter($this->contexte('POST', '/admin'))->statut);
        self::assertSame(404, $this->traiter($this->contexte('PUT', '/admin/lien'))->statut);
    }

    public function testLaCleOuvreLAdministrationEtUnNouveauLienRevoqueLAncien(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $cle = $this->traiter($this->contexte('GET', '/admin/cle/' . self::CLE_ADMIN, ['cm_acces' => $acces]));
        self::assertSame(303, $cle->statut);
        self::assertSame('/admin', $cle->entetes['Location']);
        $admin = $this->cookiesPoses($cle)['cm_admin'];

        $tableau = $this->traiter($this->contexte('GET', '/admin', ['cm_acces' => $acces, 'cm_admin' => $admin]));
        self::assertSame(200, $tableau->statut);
        self::assertStringContainsString('version 1', $tableau->corps);
        self::assertStringNotContainsString(self::CLE_ADMIN, $tableau->corps);

        $csrf = Chores\base64url(random_bytes(32));
        $nouveau = $this->traiter($this->contexte(
            'POST',
            '/admin/lien',
            ['cm_acces' => $acces, 'cm_admin' => $admin, 'cm_csrf' => $csrf],
            ['csrf' => Chores\champ_csrf_attendu(self::SECRET, $csrf), 'version' => '1']
        ));
        self::assertSame(200, $nouveau->statut);
        self::assertSame('no-store', $nouveau->entetes['Cache-Control']);
        self::assertSame(1, preg_match('#http://127\.0\.0\.1:8095/e/([A-Za-z0-9_-]{43})#', $nouveau->corps, $m));
        self::assertTrue(Chores\jeton_valide($this->pdo, $m[1]));
        self::assertFalse(Chores\jeton_valide($this->pdo, $this->jeton));
        self::assertStringContainsString('Jessica et Stéphane', $nouveau->corps);

        $ancien = $this->traiter($this->contexte('GET', '/', ['cm_acces' => $acces]));
        self::assertSame(302, $ancien->statut);
        self::assertSame('/lien', $ancien->entetes['Location']);
    }

    public function testUnFormulaireDeNouveauLienRenvoyeNeRemplacePasLeLienDiffuse(): void
    {
        $acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $admin = Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN);
        $csrf = Chores\base64url(random_bytes(32));
        $cookies = ['cm_acces' => $acces, 'cm_admin' => $admin, 'cm_csrf' => $csrf];
        $champs = ['csrf' => Chores\champ_csrf_attendu(self::SECRET, $csrf), 'version' => '1'];

        $cree = $this->traiter($this->contexte('POST', '/admin/lien', $cookies, $champs));
        self::assertSame(200, $cree->statut);
        $diffuse = Chores\reglage($this->pdo, 'jeton_hash');
        // L'appareil de Patrice reçoit le cookie d'accès de la version 2.
        self::assertTrue(Chores\cookie_acces_valide(self::SECRET, 2, $this->cookiesPoses($cree)['cm_acces']));

        // Rechargement : même formulaire (version 1) avec le cookie reçu.
        $cookies['cm_acces'] = $this->cookiesPoses($cree)['cm_acces'];
        $renvoi = $this->traiter($this->contexte('POST', '/admin/lien', $cookies, $champs));
        self::assertSame(409, $renvoi->statut);
        self::assertStringContainsString('déjà été renouvelé', $renvoi->corps);
        self::assertSame($diffuse, Chores\reglage($this->pdo, 'jeton_hash'));
        self::assertSame(2, Chores\version_lien($this->pdo));

        // Sans version, rien non plus.
        unset($champs['version']);
        self::assertSame(409, $this->traiter($this->contexte('POST', '/admin/lien', $cookies, $champs))->statut);
    }

    public function testLePrenomEstARechoisirApresUnNouveauLien(): void
    {
        $prenom = Chores\valeur_cookie_prenom(self::SECRET, 5, 1);
        Chores\nouveau_jeton($this->pdo);
        $r = $this->traiter($this->contexte('GET', '/moi', ['cm_acces' => Chores\valeur_cookie_acces(self::SECRET, 2), 'cm_prenom' => $prenom]));

        self::assertSame(302, $r->statut);
        self::assertSame('/', $r->entetes['Location']);
    }

    public function testToutesLesValeursAfficheesSontEchappees(): void
    {
        $this->pdo->exec("UPDATE cousins SET prenom = '<b>Alex</b>' WHERE id = 1");
        $r = $this->traiter($this->contexte('GET', '/', ['cm_acces' => Chores\valeur_cookie_acces(self::SECRET, 1)]));

        self::assertStringNotContainsString('<b>Alex</b>', $r->corps);
        self::assertStringContainsString('&lt;b&gt;Alex&lt;/b&gt;', $r->corps);
    }
}
