<?php

declare(strict_types=1);

/** Accès libre (config ACCES_LIBRE, 13 octobre 2026) : le site s'ouvre sans le lien secret ; l'administration garde sa clé. */
final class AccesLibreTest extends CasDeBase
{
    private function contexteLibre(string $chemin, array $cookies = [], bool $libre = true): Chores\Contexte
    {
        $config = Chores\normaliser_configuration([
            'SECRET' => self::SECRET,
            'CLE_ADMIN' => self::CLE_ADMIN,
            'BASE_PATH' => '',
            'URL_PUBLIQUE' => '',
            'ENV' => 'dev',
            'ACCES_LIBRE' => $libre,
        ]);

        return new Chores\Contexte($config, 'GET', $chemin, $cookies, [], '192.0.2.10', '', '127.0.0.1:8095', false, $this->pdo, '');
    }

    public function testSansLienCreeRienNeSOuvre(): void
    {
        $r = $this->traiter($this->contexteLibre('/'));

        self::assertSame(302, $r->statut, 'aucun lien créé : version 0, pas de cookie possible');
        self::assertSame('/lien', $r->entetes['Location'] ?? null);
    }

    public function testLeSiteSOuvreSansLeLienEtPoseLeCookie(): void
    {
        Chores\nouveau_jeton($this->pdo);
        $r = $this->traiter($this->contexteLibre('/'));

        self::assertSame(200, $r->statut);
        self::assertStringContainsString('Touche ton prénom', $r->corps);
        $poses = $this->cookiesPoses($r);
        self::assertArrayHasKey(Chores\COOKIE_ACCES, $poses);
        self::assertTrue(Chores\cookie_acces_valide(self::SECRET, 1, $poses[Chores\COOKIE_ACCES]));
    }

    public function testUnePageADemandeLePrenomPuisSOuvre(): void
    {
        Chores\nouveau_jeton($this->pdo);
        $r = $this->traiter($this->contexteLibre('/moi'));
        self::assertSame(302, $r->statut, 'sans prénom : retour au choix du prénom');
        self::assertSame('/', $r->entetes['Location'] ?? null);

        $id = (int) $this->pdo->query("SELECT id FROM cousins WHERE prenom = 'Alex'")->fetchColumn();
        $r = $this->traiter($this->contexteLibre('/moi', [Chores\COOKIE_PRENOM => Chores\valeur_cookie_prenom(self::SECRET, $id, 1)]));
        self::assertSame(200, $r->statut, 'prénom signé, même sans cookie d’accès : la page s’ouvre');
        self::assertArrayHasKey(Chores\COOKIE_ACCES, $this->cookiesPoses($r));
    }

    public function testLAdministrationGardeSaCle(): void
    {
        Chores\nouveau_jeton($this->pdo);

        self::assertSame(404, $this->traiter($this->contexteLibre('/admin'))->statut);
    }

    public function testSansLOptionLeLienResteObligatoire(): void
    {
        Chores\nouveau_jeton($this->pdo);
        $r = $this->traiter($this->contexteLibre('/', [], false));

        self::assertSame(302, $r->statut);
        self::assertSame('/lien', $r->entetes['Location'] ?? null);
        self::assertArrayNotHasKey(Chores\COOKIE_ACCES, $this->cookiesPoses($r));
    }
}
