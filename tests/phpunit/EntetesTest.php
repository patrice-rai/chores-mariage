<?php

declare(strict_types=1);

/**
 * En-têtes de sécurité : la liste construite par app/securite.php, puis les réponses réelles d'Apache dans le
 * conteneur (http://127.0.0.1, là où composer check tourne).
 */
final class EntetesTest extends CasDeBase
{
    private const ATTENDUS = [
        'content-security-policy',
        'x-content-type-options',
        'referrer-policy',
        'permissions-policy',
        'cross-origin-opener-policy',
        'x-robots-tag',
    ];

    public function testLaPolitiqueNAutoriseRienDInline(): void
    {
        $entetes = Chores\entetes_securite($this->config());
        $csp = $entetes['Content-Security-Policy'];

        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertSame('no-referrer', $entetes['Referrer-Policy']);
        self::assertArrayNotHasKey('Strict-Transport-Security', $entetes, 'pas de HSTS en développement');
    }

    public function testHstsEtMiseANiveauEnProduction(): void
    {
        $entetes = Chores\entetes_securite($this->config('/chores', 'production'));

        self::assertSame('max-age=31536000', $entetes['Strict-Transport-Security']);
        self::assertStringContainsString('upgrade-insecure-requests', $entetes['Content-Security-Policy']);
    }

    /** @return array<string, array{0: string, 1: list<int>}> */
    public static function adresses(): array
    {
        return [
            'entrée sans lien' => ['/', [302]],
            'lien manquant' => ['/lien', [200]],
            'page inconnue' => ['/page-qui-n-existe-pas', [404]],
            // 429 si la limite d'essais de l'adresse locale est atteinte : les en-têtes doivent y être aussi.
            'faux lien' => ['/e/' . str_repeat('A', 43), [303, 429]],
            'administration' => ['/admin', [404]],
            'méthode refusée' => ['/prenom', [405]],
            'feuille de style' => ['/css/base.css', [200]],
            'robots' => ['/robots.txt', [200]],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('adresses')]
    /** @param list<int> $statuts */
    public function testLesEntetesSontPresentsSurLesReponsesReelles(string $chemin, array $statuts): void
    {
        [$code, $entetes] = $this->demander($chemin);

        self::assertContains($code, $statuts, $chemin);
        foreach (self::ATTENDUS as $nom) {
            self::assertArrayHasKey($nom, $entetes, "{$nom} sur {$chemin}");
            self::assertCount(1, $entetes[$nom], "{$nom} en double sur {$chemin}");
        }
        self::assertStringNotContainsString('unsafe-inline', $entetes['content-security-policy'][0]);
        self::assertArrayNotHasKey('x-powered-by', $entetes);
    }

    public function testLesFichiersSensiblesNeSontPasServis(): void
    {
        foreach (['/index.php.bak', '/.htaccess', '/chemin-app.php', '/../config.php', '/.git/config'] as $chemin) {
            [$code] = $this->demander($chemin);
            self::assertContains($code, [400, 403, 404], $chemin);
        }
    }

    public function testLesPagesPriveesNeSontJamaisMisesEnCache(): void
    {
        [, $entetes] = $this->demander('/lien');

        self::assertSame('no-store', $entetes['cache-control'][0]);
        self::assertStringContainsString('noindex', $entetes['x-robots-tag'][0]);
    }

    /** @return array{0: int, 1: array<string, list<string>>} */
    private function demander(string $chemin): array
    {
        $flux = @fopen('http://127.0.0.1' . $chemin, 'r', false, stream_context_create(['http' => [
            'method' => 'GET',
            'follow_location' => 0,
            'ignore_errors' => true,
            'timeout' => 30,
        ]]));
        if ($flux === false) {
            self::fail('Le site ne répond pas sur http://127.0.0.1 : lancer composer check dans le conteneur (docker compose exec -T web composer check).');
        }
        $meta = stream_get_meta_data($flux);
        fclose($flux);
        $lignes = $meta['wrapper_data'] ?? [];
        preg_match('#^HTTP/\S+\s+(\d{3})#', (string) ($lignes[0] ?? ''), $m);
        $entetes = [];
        foreach (array_slice($lignes, 1) as $ligne) {
            if (str_contains((string) $ligne, ':')) {
                [$nom, $valeur] = explode(':', (string) $ligne, 2);
                $entetes[strtolower(trim($nom))][] = trim($valeur);
            }
        }

        return [(int) ($m[1] ?? 0), $entetes];
    }
}
