<?php

declare(strict_types=1);

final class CsrfTest extends CasDeBase
{
    private string $jeton;
    private string $acces;

    protected function setUp(): void
    {
        parent::setUp();
        Chores\nouveau_jeton($this->pdo);
        $this->acces = Chores\valeur_cookie_acces(self::SECRET, 1);
        $this->jeton = Chores\base64url(random_bytes(32));
    }

    public function testFonctionDeVerification(): void
    {
        $champ = Chores\champ_csrf_attendu(self::SECRET, $this->jeton);

        self::assertTrue(Chores\csrf_valide(self::SECRET, $this->jeton, $champ));
        self::assertFalse(Chores\csrf_valide(self::SECRET, null, $champ), 'cookie absent');
        self::assertFalse(Chores\csrf_valide(self::SECRET, $this->jeton, null), 'champ absent');
        self::assertFalse(Chores\csrf_valide(self::SECRET, $this->jeton, 'faux'), 'champ faux');
        self::assertFalse(Chores\csrf_valide(self::SECRET, $this->jeton, $this->jeton), 'jeton recopié tel quel');
        self::assertFalse(Chores\csrf_valide(self::SECRET, 'court', Chores\champ_csrf_attendu(self::SECRET, 'court')));
    }

    public function testPostSansJetonRefuse(): void
    {
        $r = $this->traiter($this->contexte('POST', '/prenom', ['cm_acces' => $this->acces], ['cousin' => '5']));

        self::assertSame(400, $r->statut);
        self::assertArrayNotHasKey('cm_prenom', $this->cookiesPoses($r));
    }

    public function testPostAvecFauxJetonRefuse(): void
    {
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $this->acces, 'cm_csrf' => $this->jeton],
            ['cousin' => '5', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, Chores\base64url(random_bytes(32)))]
        ));

        self::assertSame(400, $r->statut);
    }

    public function testPostDUneAutreOrigineRefuse(): void
    {
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $this->acces, 'cm_csrf' => $this->jeton],
            ['cousin' => '5', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, $this->jeton)],
            '',
            'https://ailleurs.test'
        ));

        self::assertSame(400, $r->statut);
    }

    public function testPostVenuDUnAutreSiteRefuseMemeAvecOrigineNulle(): void
    {
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $this->acces, 'cm_csrf' => $this->jeton],
            ['cousin' => '5', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, $this->jeton)],
            '',
            'null',
            'cross-site'
        ));

        self::assertSame(400, $r->statut);
    }

    public function testOrigineNulleDeNosFormulairesAcceptee(): void
    {
        // Referrer-Policy: no-referrer → le navigateur envoie « Origin: null » sur nos propres formulaires.
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $this->acces, 'cm_csrf' => $this->jeton],
            ['cousin' => '5', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, $this->jeton)],
            '',
            'null',
            'same-origin'
        ));

        self::assertSame(303, $r->statut);
    }

    public function testPostAvecBonJetonAccepte(): void
    {
        $r = $this->traiter($this->contexte(
            'POST',
            '/prenom',
            ['cm_acces' => $this->acces, 'cm_csrf' => $this->jeton],
            ['cousin' => '5', 'csrf' => Chores\champ_csrf_attendu(self::SECRET, $this->jeton)],
            '',
            'http://127.0.0.1:8095'
        ));

        self::assertSame(303, $r->statut);
        self::assertSame('/moi', $r->entetes['Location']);
        self::assertSame(5, Chores\lire_cookie_prenom(self::SECRET, $this->cookiesPoses($r)['cm_prenom'], 1));
    }

    public function testLeFormulaireDeLEntreePoseLeCookieCsrf(): void
    {
        $r = $this->traiter($this->contexte('GET', '/', ['cm_acces' => $this->acces]));

        self::assertSame(200, $r->statut);
        $jeton = $this->cookiesPoses($r)['cm_csrf'] ?? '';
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $jeton);
        self::assertStringContainsString('value="' . Chores\champ_csrf_attendu(self::SECRET, $jeton) . '"', $r->corps);
    }
}
