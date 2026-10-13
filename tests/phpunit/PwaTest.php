<?php

declare(strict_types=1);

/** PWA (spec § 8) : service worker servi par PHP, page « Hors ligne » publique, manifeste et icônes. */
final class PwaTest extends CasDeBase
{
    private function obtenirPublic(string $chemin, string $base = ''): Chores\Reponse
    {
        return $this->traiter($this->contexte('GET', $base . $chemin, [], [], $base));
    }

    public function testServiceWorkerSansCookie(): void
    {
        $r = $this->obtenirPublic('/sw.js');
        self::assertSame(200, $r->statut);
        self::assertSame('text/javascript; charset=utf-8', $r->entetes['Content-Type']);
        self::assertSame('no-cache', $r->entetes['Cache-Control']);
        self::assertStringNotContainsString('__VERSION__', $r->corps);
        self::assertStringNotContainsString('__PRECACHE__', $r->corps);
        self::assertStringContainsString('const VERSION = "' . Chores\Controleurs\version_service_worker() . '"', $r->corps);
        self::assertMatchesRegularExpression('/const VERSION = "[0-9a-f]{12}"/', $r->corps);
        self::assertStringContainsString('const BASE = "/"', $r->corps);
        self::assertStringContainsString('"/css/jetons.css"', $r->corps);
        self::assertStringContainsString('"/hors-ligne"', $r->corps);
        // Jamais de POST touché, vidéos jamais gardées sans demande.
        self::assertStringContainsString("if (requete.method !== 'GET') return", $r->corps);
    }

    public function testServiceWorkerSousBasePath(): void
    {
        $r = $this->obtenirPublic('/sw.js', '/chores');
        self::assertStringContainsString('const BASE = "/chores/"', $r->corps);
        self::assertStringContainsString('"/chores/css/jetons.css"', $r->corps);
        self::assertStringContainsString('"/chores/hors-ligne"', $r->corps);
    }

    public function testChaqueFichierPrecacheExiste(): void
    {
        foreach (Chores\Controleurs\PRECACHE as $chemin) {
            self::assertFileExists(Chores\RACINE . '/public' . $chemin);
        }
    }

    public function testPageHorsLignePubliqueSansFormulaire(): void
    {
        $r = $this->obtenirPublic('/hors-ligne');
        self::assertSame(200, $r->statut);
        self::assertStringContainsString('<h1 class="titre-ecran">Pas de réseau</h1>', $r->corps);
        self::assertStringContainsString('les vidéos gardées se lisent sans réseau', $r->corps);
        self::assertStringNotContainsString('<form', $r->corps);
        self::assertSame([], $r->cookies, 'aucun cookie posé');
    }

    public function testManifesteEtIcones(): void
    {
        $manifeste = json_decode((string) file_get_contents(Chores\RACINE . '/public/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Chorés du mariage', 'Chorés', 'moi', './', 'standalone', 'fr'], [
            $manifeste['name'], $manifeste['short_name'], $manifeste['start_url'], $manifeste['scope'], $manifeste['display'], $manifeste['lang'],
        ]);
        $jetons = Chores\lire_jetons((string) file_get_contents(Chores\RACINE . '/public/css/jetons.css'));
        self::assertSame($jetons['clair']['grenat'], $manifeste['theme_color'], 'couleur du thème = jeton grenat');
        self::assertSame($jetons['clair']['fond'], $manifeste['background_color'], 'fond = jeton fond');
        foreach ($manifeste['icons'] as $icone) {
            $infos = getimagesize(Chores\RACINE . '/public/' . $icone['src']);
            self::assertNotFalse($infos);
            self::assertSame($icone['sizes'], $infos[0] . 'x' . $infos[1]);
        }
        self::assertStringContainsString('maskable', implode(' ', array_column($manifeste['icons'], 'purpose')));
    }

    public function testLeTelephoneOublieSeulementQuandSonAccesEstPerime(): void
    {
        Chores\nouveau_jeton($this->pdo);
        $perime = Chores\valeur_cookie_acces(self::SECRET, Chores\version_lien($this->pdo));
        Chores\nouveau_jeton($this->pdo);
        $r = $this->traiter($this->contexte('GET', '/lien', ['cm_acces' => $perime]));
        self::assertSame('1', $r->entetes['X-Chores-Oublier'] ?? null);
        self::assertStringContainsString('data-oublier-telephone', $r->corps);

        // Un simple lien vers /lien (sans cookie, ou avec un cookie encore bon) ne vide rien.
        foreach ([[], ['cm_acces' => Chores\valeur_cookie_acces(self::SECRET, Chores\version_lien($this->pdo))]] as $cookies) {
            $r = $this->traiter($this->contexte('GET', '/lien', $cookies));
            self::assertArrayNotHasKey('X-Chores-Oublier', $r->entetes);
            self::assertStringNotContainsString('data-oublier-telephone', $r->corps);
        }
    }

    public function testLeGabaritAnnonceLeManifeste(): void
    {
        $r = $this->obtenirPublic('/lien');
        self::assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest">', $r->corps);
        self::assertStringContainsString('<link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">', $r->corps);
    }
}
