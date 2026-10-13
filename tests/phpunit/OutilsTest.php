<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Contrôles de composer check : contrastes des jetons, lint inline (couleurs hors jetons, styles et scripts
// inline), budgets de poids (polices, CSS, JS ; spec § 13).
final class OutilsTest extends TestCase
{
    private const RACINE = __DIR__ . '/../..';

    private string $temporaire;

    protected function setUp(): void
    {
        $this->temporaire = sys_get_temp_dir() . '/chores-outils-' . bin2hex(random_bytes(6));
        mkdir($this->temporaire, 0700, true);
    }

    protected function tearDown(): void
    {
        $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->temporaire, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterateur as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->temporaire);
    }

    /** @return array{0: int, 1: string} code de sortie et sorties */
    private function lancer(string $script, string $argument): array
    {
        exec('php ' . escapeshellarg(self::RACINE . '/scripts/' . $script) . ' ' . escapeshellarg($argument) . ' 2>&1', $sortie, $code);

        return [$code, implode("\n", $sortie)];
    }

    private function jetonsAvec(string $remplace, string $par): string
    {
        $css = (string) file_get_contents(self::RACINE . '/public/css/jetons.css');
        $fichier = $this->temporaire . '/jetons.css';
        file_put_contents($fichier, str_replace($remplace, $par, $css));

        return $fichier;
    }

    public function testContrastesDesVraisJetons(): void
    {
        [$code, $sortie] = $this->lancer('contraste.php', self::RACINE . '/public/css/jetons.css');

        self::assertSame(0, $code, $sortie);
        self::assertStringContainsString('0 échec(s)', $sortie);
    }

    public function testUnTexteSousQuatreEtDemiEchoue(): void
    {
        // Secondaire éclairci dans le thème clair : #8a7a74 sur #fffaf3 fait environ 4,1:1.
        $fichier = $this->jetonsAvec("--secondaire: #5c4a44;\n  --grenat: #8a1538;", "--secondaire: #8a7a74;\n  --grenat: #8a1538;");
        [$code, $sortie] = $this->lancer('contraste.php', $fichier);

        self::assertSame(1, $code);
        self::assertMatchesRegularExpression('/ÉCHEC clair\s+secondaire\s+sur fond/', $sortie);
    }

    public function testLOrangeDOrigineSurLaSurfaceEchoue(): void
    {
        $fichier = $this->jetonsAvec('--orange-texte: #a04a08;', '--orange-texte: #b4530a;');
        [$code, $sortie] = $this->lancer('contraste.php', $fichier);

        self::assertSame(1, $code);
        self::assertStringContainsString('orange-texte', $sortie);
    }

    public function testBlocsSombresDifferentsEchouent(): void
    {
        $css = (string) file_get_contents(self::RACINE . '/public/css/jetons.css');
        $position = strpos($css, '@media (prefers-color-scheme: dark)');
        self::assertNotFalse($position);
        $modifie = substr($css, 0, $position) . str_replace('--secondaire: #cdbfb6;', '--secondaire: #cdbfb7;', substr($css, $position));
        $fichier = $this->temporaire . '/jetons.css';
        file_put_contents($fichier, $modifie);
        [$code, $sortie] = $this->lancer('contraste.php', $fichier);

        self::assertSame(1, $code);
        self::assertStringContainsString('diffère', $sortie);
    }

    private function projetFactice(string $vue, string $css, string $js = ''): string
    {
        mkdir($this->temporaire . '/app/vues', 0700, true);
        mkdir($this->temporaire . '/public/css', 0700, true);
        mkdir($this->temporaire . '/public/js', 0700, true);
        file_put_contents($this->temporaire . '/app/vues/page.php', $vue);
        file_put_contents($this->temporaire . '/public/css/page.css', $css);
        file_put_contents($this->temporaire . '/public/css/jetons.css', ":root { --fond: #fffaf3; }\n");
        file_put_contents($this->temporaire . '/public/js/page.js', $js);

        return $this->temporaire;
    }

    public function testLintInlinePropre(): void
    {
        [$code, $sortie] = $this->lancer('lint-inline.php', $this->projetFactice('<p class="a" id="b"><a href="#contenu">x</a></p>', '.a { color: var(--fond); }'));

        self::assertSame(0, $code, $sortie);
    }

    public function testLintInlineRefuseUnAttributStyle(): void
    {
        [$code, $sortie] = $this->lancer('lint-inline.php', $this->projetFactice('<p style="color: red">x</p>', ''));

        self::assertSame(1, $code);
        self::assertStringContainsString('attribut style', $sortie);
    }

    public function testLintInlineRefuseUnScriptInlineEtUnGestionnaire(): void
    {
        [$code, $sortie] = $this->lancer('lint-inline.php', $this->projetFactice('<script>alert(1)</script><button onclick="x()">x</button>', ''));

        self::assertSame(1, $code);
        self::assertStringContainsString('script inline', $sortie);
        self::assertStringContainsString('gestionnaire on*=', $sortie);
    }

    public function testLintInlineRefuseUneCouleurHorsJetons(): void
    {
        [$code, $sortie] = $this->lancer('lint-inline.php', $this->projetFactice('<p>x</p>', '.a { color: #8a1538; }', "el.style.color = 'rgb(1, 2, 3)'"));

        self::assertSame(1, $code);
        self::assertStringContainsString('public/css/page.css:1 : couleur hors jetons', str_replace('\\', '/', $sortie));
        self::assertStringContainsString('public/js/page.js:1 : couleur hors jetons', str_replace('\\', '/', $sortie));
    }

    /** @param list<string> $fichiers */
    private function poids(array $fichiers): int
    {
        return array_sum(array_map(static fn (string $f): int => (int) filesize($f), $fichiers));
    }

    public function testBudgets(): void
    {
        $polices = glob(self::RACINE . '/public/polices/*.woff2') ?: [];
        self::assertCount(4, $polices);
        self::assertLessThanOrEqual(120 * 1024, $this->poids($polices), 'polices ≤ 120 Ko');

        // CSS : budget de 40 Ko mesuré une fois compressé (ce qui transite : .htaccess compresse les feuilles),
        // avec un garde-fou de 72 Ko sans compression (60 Ko jusqu'à la phase 4 ; la phase 5 ajoute ses écrans).
        $css = glob(self::RACINE . '/public/css/*.css') ?: [];
        $cssPages = array_values(array_filter($css, static fn (string $f): bool => basename($f) !== 'gabarits.css'));
        $compresse = array_sum(array_map(static fn (string $f): int => strlen((string) gzencode((string) file_get_contents($f), 6)), $cssPages));
        self::assertLessThanOrEqual(40 * 1024, $compresse, 'CSS des pages ≤ 40 Ko compressé');
        self::assertLessThanOrEqual(72 * 1024, $this->poids($cssPages), 'CSS des pages ≤ 72 Ko sans compression');

        $lecteur = array_map(static fn (string $f): string => self::RACINE . '/public/js/' . $f, ['lecteur.js', 'lecteur-calc.js', 'decompte.js']);
        self::assertLessThanOrEqual(25 * 1024, (int) filesize($lecteur[0]), 'lecteur.js ≤ 25 Ko');
        self::assertLessThanOrEqual(40 * 1024, $this->poids([...$lecteur, self::RACINE . '/public/js/site.js']), 'JS ≤ 40 Ko');
    }

    public function testLicencesDesPolices(): void
    {
        $licences = (string) file_get_contents(self::RACINE . '/public/polices/LICENCES.md');

        self::assertSame(2, substr_count($licences, 'SIL OPEN FONT LICENSE Version 1.1'));
        foreach (glob(self::RACINE . '/public/polices/*.woff2') ?: [] as $police) {
            self::assertStringContainsString(basename($police), $licences);
        }
    }
}
