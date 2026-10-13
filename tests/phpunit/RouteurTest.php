<?php

declare(strict_types=1);

final class RouteurTest extends CasDeBase
{
    public function testParametresMethodesEt404(): void
    {
        $routeur = (new Chores\Routeur())
            ->ajouter('GET', '/e/{jeton}', static fn () => null, 'public')
            ->ajouter('POST', '/prenom', static fn () => null, 'acces');

        $trouve = $routeur->trouver('GET', '/e/abc_DEF-1');
        self::assertSame(200, $trouve['statut']);
        self::assertSame(['jeton' => 'abc_DEF-1'], $trouve['params']);
        self::assertSame(200, $routeur->trouver('HEAD', '/e/abc')['statut']);
        self::assertSame(['statut' => 405, 'permises' => ['POST'], 'niveaux' => ['acces']], $routeur->trouver('GET', '/prenom'));
        self::assertSame(['statut' => 404], $routeur->trouver('GET', '/e/a/b'));
        self::assertSame(['statut' => 404], $routeur->trouver('GET', '/inconnue'));
        self::assertSame(['statut' => 404], $routeur->trouver('GET', null));
    }

    public function testBasePath(): void
    {
        self::assertSame('/', Chores\retirer_base('/chores', '/chores'));
        self::assertSame('/moi', Chores\retirer_base('/chores/moi', '/chores'));
        self::assertNull(Chores\retirer_base('/choresmoi', '/chores'));
        self::assertNull(Chores\retirer_base('/autre/moi', '/chores'));
        self::assertSame('/moi', Chores\retirer_base('/moi', ''));
        self::assertSame('/chores', Chores\normaliser_base('chores/'));
        self::assertSame('', Chores\normaliser_base('/'));
        self::assertSame('/chores/moi', Chores\url($this->config('/chores'), '/moi'));
    }

    public function testBasePathInvalideRefuse(): void
    {
        $this->expectException(RuntimeException::class);
        Chores\normaliser_base('/chores"><script>');
    }

    public function testConfigurationSansSecretRefusee(): void
    {
        $this->expectException(RuntimeException::class);
        Chores\normaliser_configuration(['SECRET' => 'court', 'ENV' => 'dev']);
    }

    public function testCleAdminTropCourteDesactiveLAdministration(): void
    {
        $config = Chores\normaliser_configuration(['SECRET' => self::SECRET, 'CLE_ADMIN' => 'courte', 'ENV' => 'dev']);

        self::assertSame('', $config['CLE_ADMIN']);
        self::assertFalse(Chores\cle_admin_valide($config['CLE_ADMIN'], 'courte'));
    }

    public function testUneAdresseEncodeeNEstPasDecodee(): void
    {
        $_SERVER['REQUEST_URI'] = '/%65/' . str_repeat('A', 43) . '?x=1';
        $c = Chores\Contexte::depuisGlobales($this->config());
        unset($_SERVER['REQUEST_URI']);

        self::assertSame('/%65/' . str_repeat('A', 43), $c->chemin);
        self::assertSame(404, $this->traiter($c)->statut);
    }

    public function test405IndiqueLesMethodesPermises(): void
    {
        $r = $this->traiter($this->contexte('GET', '/prenom'));

        self::assertSame(405, $r->statut);
        self::assertSame('POST', $r->entetes['Allow']);
    }
}
