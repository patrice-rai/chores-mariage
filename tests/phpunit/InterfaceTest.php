<?php

declare(strict_types=1);

// Phase 3 : bascule de thème, /gabarits, mode répétition « Démo », service de la vidéo par plages d'octets.
final class InterfaceTest extends CasDeBase
{
    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $post
     * @param array<string, string> $requete
     * @param array<string, string> $entetes
     */
    private function requete(string $methode, string $chemin, array $cookies = [], array $post = [], array $requete = [], array $entetes = [], string $env = 'dev'): Chores\Contexte
    {
        return new Chores\Contexte($this->config('', $env), $methode, $chemin, $cookies, $post, '192.0.2.10', '', '127.0.0.1:8095', false, $this->pdo, 'same-origin', $requete, $entetes);
    }

    /** @return array{0: array<string, string>, 1: string} cookie CSRF et champ attendu */
    private function csrf(): array
    {
        $jeton = Chores\base64url(random_bytes(32));

        return [['cm_csrf' => $jeton], Chores\champ_csrf_attendu(self::SECRET, $jeton)];
    }

    public function testBasculeDeThemePoseLeCookieEtRevientALaPage(): void
    {
        [$cookies, $champ] = $this->csrf();
        $r = $this->traiter($this->requete('POST', '/theme', $cookies, ['csrf' => $champ, 'theme' => 'sombre', 'retour' => '/gabarits?section=2']));

        self::assertSame(303, $r->statut);
        self::assertSame('/gabarits?section=2', $r->entetes['Location']);
        self::assertSame('sombre', $this->cookiesPoses($r)['cm_theme']);
    }

    public function testBasculeEnJavaScriptRepond204(): void
    {
        [$cookies, $champ] = $this->csrf();
        $r = $this->traiter($this->requete('POST', '/theme', $cookies, ['csrf' => $champ, 'theme' => 'clair'], [], ['accept' => 'application/json']));

        self::assertSame(204, $r->statut);
        self::assertSame('clair', $this->cookiesPoses($r)['cm_theme']);
    }

    public function testBasculeSansJetonCsrfRefusee(): void
    {
        $r = $this->traiter($this->requete('POST', '/theme', [], ['theme' => 'sombre']));

        self::assertSame(400, $r->statut);
        self::assertArrayNotHasKey('cm_theme', $this->cookiesPoses($r));
    }

    public function testThemeInconnuEffaceLeChoix(): void
    {
        [$cookies, $champ] = $this->csrf();
        $r = $this->traiter($this->requete('POST', '/theme', $cookies, ['csrf' => $champ, 'theme' => 'rose', 'retour' => '/']));

        self::assertSame('', $this->cookiesPoses($r)['cm_theme']);
    }

    public function testRetourLimiteAuxCheminsInternes(): void
    {
        foreach (['//exemple.org', '/\\exemple.org', 'https://exemple.org', '/a/../admin', '/a b', 'javascript:alert(1)', '', null] as $mauvais) {
            self::assertSame('/', Chores\Controleurs\chemin_de_retour($mauvais), var_export($mauvais, true));
        }
        self::assertSame('/moi', Chores\Controleurs\chemin_de_retour('/moi'));
        self::assertSame('/gabarits?section=2&surligner=0', Chores\Controleurs\chemin_de_retour('/gabarits?section=2&surligner=0'));
    }

    public function testLeGabaritPoseLeThemeChoisi(): void
    {
        $sombre = $this->traiter($this->requete('GET', '/gabarits', ['cm_theme' => 'sombre']));
        $libre = $this->traiter($this->requete('GET', '/gabarits'));
        $faux = $this->traiter($this->requete('GET', '/gabarits', ['cm_theme' => 'bleu']));

        self::assertStringContainsString('<html lang="fr" data-theme="sombre">', $sombre->corps);
        self::assertStringContainsString('<html lang="fr">', $libre->corps);
        self::assertStringContainsString('<html lang="fr">', $faux->corps);
    }

    public function testGabaritsLibresEnDeveloppement(): void
    {
        $r = $this->traiter($this->requete('GET', '/gabarits'));

        self::assertSame(200, $r->statut);
        self::assertStringContainsString('<h1 class="titre-ecran">Gabarits</h1>', $r->corps);
        self::assertStringContainsString('data-lecteur', $r->corps);
        self::assertStringNotContainsString(' style=', $r->corps);
    }

    public function testGabaritsReservesAPatriceEnProduction(): void
    {
        $sans = $this->traiter($this->requete('GET', '/gabarits', [], [], [], [], 'production'));
        self::assertSame(404, $sans->statut);
        self::assertSame(404, $this->traiter($this->requete('GET', '/gabarits/demo.webm', [], [], [], [], 'production'))->statut);
        self::assertSame(404, $this->traiter($this->requete('GET', '/gabarits/repeter', [], [], [], [], 'production'))->statut);

        Chores\nouveau_jeton($this->pdo);
        $avec = $this->traiter($this->requete('GET', '/gabarits', [
            'cm_acces' => Chores\valeur_cookie_acces(self::SECRET, 1),
            'cm_admin' => Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN),
        ], [], [], [], 'production'));
        self::assertSame(200, $avec->statut);
    }

    public function testModeRepetitionToujoursSombre(): void
    {
        $r = $this->traiter($this->requete('GET', '/gabarits/repeter', ['cm_theme' => 'clair'], [], ['section' => '2']));

        self::assertSame(200, $r->statut);
        self::assertStringContainsString('<html lang="fr" data-theme="sombre">', $r->corps);
        self::assertStringContainsString('data-courante="1"', $r->corps);
        self::assertStringContainsString('Section suivante', $r->corps);
        self::assertStringNotContainsString('bascule-theme', $r->corps);
    }

    public function testModeRepetitionSectionHorsLimitesEtAutreChanson(): void
    {
        $r = $this->traiter($this->requete('GET', '/gabarits/repeter', [], [], ['section' => '99']));
        self::assertStringContainsString('data-courante="2"', $r->corps);
        $r = $this->traiter($this->requete('GET', '/gabarits/repeter', [], [], ['section' => '-1']));
        self::assertStringContainsString('data-courante="0"', $r->corps);
        // Les vraies chansons demandent le lien et un prénom (ChansonsTest).
        self::assertSame(302, $this->traiter($this->requete('GET', '/chanson/kanimaa/repeter'))->statut);
    }

    public function testPlagesHttp(): void
    {
        self::assertNull(Chores\plage_http(null, 1000));
        self::assertNull(Chores\plage_http('', 1000));
        self::assertSame([0, 99], Chores\plage_http('bytes=0-99', 1000));
        self::assertSame([500, 999], Chores\plage_http('bytes=500-', 1000));
        self::assertSame([900, 999], Chores\plage_http('bytes=-100', 1000));
        self::assertSame([0, 999], Chores\plage_http('bytes=-5000', 1000));
        self::assertSame([990, 999], Chores\plage_http('bytes=990-5000', 1000));
        self::assertFalse(Chores\plage_http('bytes=1000-', 1000));
        self::assertFalse(Chores\plage_http('bytes=50-10', 1000));
        self::assertFalse(Chores\plage_http('bytes=-0', 1000));
        self::assertNull(Chores\plage_http('bytes=0-1,5-9', 1000), 'plusieurs plages : fichier entier');
        self::assertNull(Chores\plage_http('lignes=0-9', 1000));
    }

    public function testServirUnFichierParPlage(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'chores');
        file_put_contents($fichier, str_repeat('abcdefghij', 10));
        try {
            $entier = Chores\servir_fichier($this->requete('GET', '/x'), $fichier, 'video/webm');
            self::assertSame(200, $entier->statut);
            self::assertSame('100', $entier->entetes['Content-Length']);
            self::assertSame('bytes', $entier->entetes['Accept-Ranges']);
            self::assertSame('video/webm', $entier->entetes['Content-Type']);

            $plage = Chores\servir_fichier($this->requete('GET', '/x', [], [], [], ['range' => 'bytes=10-19']), $fichier, 'video/webm');
            self::assertSame(206, $plage->statut);
            self::assertSame('bytes 10-19/100', $plage->entetes['Content-Range']);
            self::assertSame(['chemin' => $fichier, 'debut' => 10, 'longueur' => 10], $plage->fichier);

            $impossible = Chores\servir_fichier($this->requete('GET', '/x', [], [], [], ['range' => 'bytes=200-']), $fichier, 'video/webm');
            self::assertSame(416, $impossible->statut);
            self::assertSame('bytes */100', $impossible->entetes['Content-Range']);
        } finally {
            unlink($fichier);
        }
        self::assertSame(404, Chores\servir_fichier($this->requete('GET', '/x'), '/nulle/part.webm', 'video/webm')->statut);
    }

    public function testFormats(): void
    {
        self::assertSame('0:17', Chores\format_temps(17.9));
        self::assertSame('1:02:05', Chores\format_temps(3725));
        self::assertSame('4 min 04', Chores\format_duree(244));
        self::assertSame('durée inconnue', Chores\format_duree(null));
        self::assertSame('0:17', Chores\format_duree_section(0.0, 16.9));
        self::assertSame('0,75 ×', Chores\format_vitesse(0.75));
        self::assertSame('côté jardin (à gauche), au fond', Chores\position_en_mots(15, 35));
        self::assertSame('au centre, devant', Chores\position_en_mots(50, 70));
        self::assertSame('Sté', Chores\prenom_abrege('Stéphane'));
        self::assertSame(7, Chores\pas_de_cinq(35));
        self::assertSame(20, Chores\pas_de_cinq(140));
    }

    public function testDonneesDeDemonstrationSansFait(): void
    {
        $chanson = Chores\demo_chanson();
        self::assertSame('Démo', $chanson['titre']);
        self::assertNull($chanson['bpm'], 'tempo de démonstration inconnu : 120 par défaut et « tempo à régler »');
        self::assertSame(['Section A', 'Section B', 'Section C'], array_column(Chores\demo_sections(), 'nom'));
        foreach (Chores\demo_placements() as $points) {
            self::assertCount(8, $points);
            self::assertNotContains('Nadine', array_column($points, 'prenom'));
        }
    }

    public function testPagesReserveesIntrouvablesEnProductionQuelleQueSoitLaMethode(): void
    {
        [$cookies, $champ] = $this->csrf();
        $essai = $this->traiter($this->requete('POST', '/gabarits/essai', $cookies, ['csrf' => $champ], [], [], 'production'));
        self::assertSame(404, $essai->statut, 'POST avec jeton CSRF, sans cookie d’administration');
        self::assertSame(404, $this->traiter($this->requete('POST', '/gabarits/essai', [], [], [], [], 'production'))->statut, 'POST sans jeton');
        self::assertSame(404, $this->traiter($this->requete('PUT', '/gabarits', [], [], [], [], 'production'))->statut);
        self::assertSame(404, $this->traiter($this->requete('GET', '/gabarits/essai', [], [], [], [], 'production'))->statut);
        // En développement, une méthode refusée reste un 405 ordinaire.
        self::assertSame(405, $this->traiter($this->requete('PUT', '/gabarits'))->statut);
    }

    public function testRetourNeRecopieQueLesParametresConnus(): void
    {
        $c = $this->requete('GET', '/gabarits', [], [], ['section' => '2', 'x' => 'texte-choisi', 'surligner' => '0', 'section2' => '1']);
        self::assertSame('/gabarits?section=2&surligner=0', $c->ici());
        self::assertSame('/gabarits', $this->requete('GET', '/gabarits', [], [], ['section' => '<b>'])->ici());
        $page = $this->traiter($this->requete('GET', '/gabarits', [], [], ['x' => 'texte-choisi']));
        self::assertStringNotContainsString('texte-choisi', $page->corps);
    }

    public function testGabaritsLibresSeulementSurLePosteLocal(): void
    {
        self::assertTrue(Chores\hote_local('127.0.0.1:8095'));
        self::assertTrue(Chores\hote_local('localhost'));
        self::assertTrue(Chores\hote_local('[::1]:8095'));
        self::assertFalse(Chores\hote_local('exemple.org'));
        self::assertFalse(Chores\hote_local("127.0.0.1\n"));
        // ENV « dev » oublié chez un hébergeur : les gabarits restent fermés.
        $distant = new Chores\Contexte($this->config(), 'GET', '/gabarits', [], [], '192.0.2.10', '', 'exemple.org', true, $this->pdo, 'none');
        self::assertSame(404, $this->traiter($distant)->statut);
    }

    public function testFinsDeChaineStrictes(): void
    {
        self::assertSame('/', Chores\Controleurs\chemin_de_retour("/moi\n"));
        self::assertFalse(Chores\lien_sortant_valide("https://youtu.be/x\n"));
        self::assertSame('/gabarits', $this->requete('GET', '/gabarits', [], [], ['section' => "2\n"])->ici());
    }

    public function testLiensSortantsEnHttpsSeulement(): void
    {
        self::assertTrue(Chores\lien_sortant_valide('https://youtu.be/Sr5hYJ7vzlM'));
        self::assertTrue(Chores\lien_sortant_valide('https://framadate.org/'));
        foreach (['javascript:alert(1)', 'data:text/html,x', 'http://youtu.be/x', '//youtu.be/x', 'https://a b', null, ''] as $mauvais) {
            self::assertFalse(Chores\lien_sortant_valide($mauvais), var_export($mauvais, true));
        }
    }
}
