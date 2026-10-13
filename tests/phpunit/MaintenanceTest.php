<?php

declare(strict_types=1);

/**
 * Phase 6 : installation sans SSH, mises à jour de la base, sauvegarde et restauration, journal sans adresse IP.
 * Les bases sur disque vivent dans un dossier temporaire du conteneur, jamais dans stockage/.
 */
final class MaintenanceTest extends CasDeBase
{
    private string $dossier = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = sys_get_temp_dir() . '/chores-maintenance-' . bin2hex(random_bytes(6));
        mkdir($this->dossier, 0700, true);
    }

    protected function tearDown(): void
    {
        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dossier, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterateur as $element) {
            $element->isDir() ? rmdir($element->getPathname()) : unlink($element->getPathname());
        }
        rmdir($this->dossier);
    }

    private function contexteSur(PDO $pdo, string $methode, string $chemin, array $cookies = [], array $post = [], string $cle = self::CLE_ADMIN): Chores\Contexte
    {
        return new Chores\Contexte(
            Chores\normaliser_configuration(['SECRET' => self::SECRET, 'CLE_ADMIN' => $cle, 'BASE_PATH' => '/chores', 'ENV' => 'production', 'URL_PUBLIQUE' => 'https://exemple.test']),
            $methode,
            Chores\retirer_base($chemin, '/chores'),
            $cookies,
            $post,
            '198.51.100.23',
            '',
            'exemple.test',
            true,
            $pdo,
            'same-origin',
        );
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} cookies et champ CSRF d'un formulaire */
    private function formulaire(): array
    {
        $csrf = Chores\base64url(random_bytes(32));

        return [['cm_csrf' => $csrf], ['csrf' => Chores\champ_csrf_attendu(self::SECRET, $csrf)]];
    }

    public function testInstallationSurUneBaseNeuve(): void
    {
        $pdo = Chores\connexion(':memory:');
        self::assertFalse(Chores\base_installee($pdo));
        self::assertSame(['001-socle', '002-donnees', '003-groupe'], Chores\migrations_en_attente($pdo));

        $formulaire = Chores\traiter($this->contexteSur($pdo, 'GET', '/chores/installer'), Chores\routes());
        self::assertSame(200, $formulaire->statut);
        self::assertSame('no-store', $formulaire->entetes['Cache-Control']);
        self::assertStringContainsString('type="password"', $formulaire->corps);
        self::assertStringContainsString('action="/chores/installer"', $formulaire->corps);

        // Clé vide, puis mauvaise clé : rien n'est créé, l'erreur est écrite, la clé n'est jamais réaffichée.
        [$cookies, $champs] = $this->formulaire();
        $vide = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => '  ']), Chores\routes());
        self::assertSame(422, $vide->statut);
        self::assertStringContainsString('colle la valeur de CLE_ADMIN', $vide->corps);
        $refus = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => 'pas-la-bonne-cle']), Chores\routes());
        self::assertSame(422, $refus->statut);
        self::assertStringContainsString('ce n’est pas la bonne', $refus->corps);
        self::assertStringNotContainsString('pas-la-bonne-cle', $refus->corps);
        self::assertSame(0, Chores\version_lien($pdo));

        // Bonne clé : base prête, faits importés, premier lien montré une fois, accès et administration posés.
        $installe = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => ' ' . self::CLE_ADMIN . ' ']), Chores\routes());
        self::assertSame(200, $installe->statut);
        self::assertSame('no-store', $installe->entetes['Cache-Control']);
        self::assertSame(1, preg_match('#https://exemple\.test/chores/e/([A-Za-z0-9_-]{43})#', $installe->corps, $m));
        self::assertTrue(Chores\jeton_valide($pdo, $m[1]));
        self::assertStringNotContainsString(self::CLE_ADMIN, $installe->corps);
        self::assertSame([], Chores\migrations_en_attente($pdo));
        self::assertGreaterThan(0, (int) $pdo->query('SELECT COUNT(*) FROM chansons')->fetchColumn());
        $poses = $this->cookiesPoses($installe);
        self::assertTrue(Chores\cookie_acces_valide(self::SECRET, 1, $poses['cm_acces']));
        self::assertTrue(Chores\cookie_admin_valide(self::SECRET, self::CLE_ADMIN, $poses['cm_admin']));
        $reglages = implode(' ', $pdo->query('SELECT valeur FROM reglages')->fetchAll(PDO::FETCH_COLUMN));
        self::assertStringNotContainsString($m[1], $reglages, 'le lien n’est gardé qu’en empreinte');

        // Ensuite, la page n'existe plus, ni en lecture ni en envoi (même avec la bonne clé).
        self::assertSame(404, Chores\traiter($this->contexteSur($pdo, 'GET', '/chores/installer'), Chores\routes())->statut);
        $encore = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => self::CLE_ADMIN]), Chores\routes());
        self::assertSame(404, $encore->statut);
        // Ni un envoi sans jeton CSRF ni une autre méthode ne trahissent que l'adresse a existé.
        self::assertSame(404, Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer'), Chores\routes())->statut);
        self::assertSame(404, Chores\traiter($this->contexteSur($pdo, 'PUT', '/chores/installer'), Chores\routes())->statut);
        self::assertTrue(Chores\jeton_valide($pdo, $m[1]), 'le premier lien reste valable');
    }

    public function testInstallationFermeeSansCleOuSansJetonCsrf(): void
    {
        $pdo = Chores\connexion(':memory:');
        self::assertSame(404, Chores\traiter($this->contexteSur($pdo, 'GET', '/chores/installer', [], [], ''), Chores\routes())->statut);
        self::assertSame(400, Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', [], ['cle' => self::CLE_ADMIN]), Chores\routes())->statut);
        self::assertFalse(Chores\base_installee($pdo), 'rien n’est préparé sans jeton CSRF');
    }

    public function testLInstallationEstLimiteeEnEssais(): void
    {
        $pdo = Chores\connexion(':memory:');
        [$cookies, $champs] = $this->formulaire();
        $statuts = [];
        for ($i = 0; $i < 11; ++$i) {
            $statuts[] = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => 'faux']), Chores\routes())->statut;
        }
        self::assertSame(array_fill(0, 10, 422), array_slice($statuts, 0, 10));
        self::assertSame(429, $statuts[10]);
    }

    public function testMiseAJourDeLaBaseDepuisLAdministration(): void
    {
        // Base restée à la version 2 : on retire ce que la migration 003 a créé.
        $this->pdo->exec('DROP TABLE constats; DROP INDEX commentaires_temps; DROP INDEX videos_album; DROP INDEX presences_fil');
        $this->pdo->exec("DELETE FROM migrations WHERE version = '003-groupe'");
        $tableau = $this->traiter($this->contexteAdmin('GET', '/admin'));
        self::assertStringContainsString('1 mise(s) à jour en attente', $tableau->corps);
        $page = $this->traiter($this->contexteAdmin('GET', '/admin/base'));
        self::assertSame(200, $page->statut);
        self::assertStringContainsString('1 mise(s) à jour en attente (003-groupe)', $page->corps);
        self::assertStringContainsString('Mettre la base à jour', $page->corps);
        self::assertStringContainsString('Version du site : ' . Chores\VERSION, $tableau->corps);

        [$cookies, $champs] = $this->formulaire();
        $r = $this->traiter($this->contexteAdmin('POST', '/admin/migrer', $cookies, $champs));
        self::assertSame(303, $r->statut);
        self::assertSame('/admin/base?ok=base', $r->entetes['Location']);
        self::assertSame([], Chores\migrations_en_attente($this->pdo));
        self::assertStringContainsString('Base : à jour.', $this->traiter($this->contexteAdmin('GET', '/admin/base'))->corps);

        $deNouveau = $this->traiter($this->contexteAdmin('POST', '/admin/migrer', $cookies, $champs));
        self::assertSame('/admin/base?ok=base-a-jour', $deNouveau->entetes['Location']);
        self::assertSame(404, $this->traiter($this->contexte('GET', '/admin/base'))->statut);
        // Sans cookie d'administration : 404.
        self::assertSame(404, $this->traiter($this->contexte('POST', '/admin/migrer', $cookies, $champs))->statut);
    }

    private function contexteAdmin(string $methode, string $chemin, array $cookies = [], array $post = []): Chores\Contexte
    {
        Chores\nouveau_jeton($this->pdo, 0);

        return $this->contexte($methode, $chemin, $cookies + [
            'cm_acces' => Chores\valeur_cookie_acces(self::SECRET, Chores\version_lien($this->pdo)),
            'cm_admin' => Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN),
        ], $post, '', '', 'same-origin');
    }

    public function testSauvegardeEtRestaurationSurUneBaseVide(): void
    {
        $base = $this->dossier . '/stockage/base.sqlite';
        $pdo = Chores\connexion($base);
        Chores\migrer($pdo);
        $pdo->exec("INSERT INTO chansons (slug, titre, statut) VALUES ('demo', 'Démo', 'proposee')");
        $pdo = null;

        $sauvegarde = Chores\sauvegarder_base($base, null, gmmktime(10, 0, 0, 10, 13, 2026));
        self::assertSame($this->dossier . '/stockage/sauvegardes/base-2026-10-13-120000.sqlite', $sauvegarde);
        self::assertSame(9, Chores\verifier_sauvegarde($sauvegarde)['cousins']);
        self::assertSame(['001-socle', '002-donnees', '003-groupe'], Chores\verifier_sauvegarde($sauvegarde)['migrations']);
        // Deux sauvegardes dans la même seconde ne s'écrasent pas.
        self::assertStringEndsWith('-120000-2.sqlite', Chores\sauvegarder_base($base, null, gmmktime(10, 0, 0, 10, 13, 2026)));

        // Perte : la base est remplacée par une base neuve, vide.
        foreach (['', '-wal', '-shm'] as $suffixe) {
            @unlink($base . $suffixe);
        }
        Chores\migrer(Chores\connexion($base));
        self::assertSame(0, (int) Chores\connexion($base)->query('SELECT COUNT(*) FROM chansons')->fetchColumn());

        $resultat = Chores\restaurer_base($sauvegarde, $base);
        self::assertNotNull($resultat['remplacee']);
        self::assertFileExists((string) $resultat['remplacee']);
        self::assertSame([], $resultat['migrations']);
        $restauree = Chores\connexion($base);
        self::assertSame('Démo', $restauree->query("SELECT titre FROM chansons WHERE slug = 'demo'")->fetchColumn());
        self::assertSame('ok', $restauree->query('PRAGMA integrity_check')->fetchColumn());
        self::assertCount(2, Chores\liste_sauvegardes($this->dossier . '/stockage/sauvegardes'));
    }

    public function testUneSauvegardeEtrangereOuAbimeeEstRefusee(): void
    {
        $texte = $this->dossier . '/texte.sqlite';
        file_put_contents($texte, str_repeat('pas une base ', 100));
        $autre = $this->dossier . '/autre.sqlite';
        $pdo = Chores\connexion($autre);
        $pdo->exec('CREATE TABLE t (x INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $pdo = null;
        $base = $this->dossier . '/stockage/base.sqlite';
        Chores\migrer(Chores\connexion($base));
        $avant = hash_file('sha256', $base);

        foreach ([$texte => 'n’est pas une base SQLite', $autre => 'table des migrations absente', $this->dossier . '/absente.sqlite' => 'introuvable'] as $fichier => $message) {
            try {
                Chores\restaurer_base($fichier, $base);
                self::fail('restauration acceptée : ' . $fichier);
            } catch (RuntimeException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
        self::assertSame($avant, hash_file('sha256', $base), 'la base actuelle n’a pas bougé');
    }

    public function testRestaurerSurUneBaseVideOuAbimeeEtGarderLeLienEnService(): void
    {
        $base = $this->dossier . '/stockage/base.sqlite';
        $pdo = Chores\connexion($base);
        Chores\migrer($pdo);
        Chores\nouveau_jeton($pdo);
        $pdo->exec("INSERT INTO chansons (slug, titre, statut) VALUES ('demo', 'Démo', 'proposee')");
        $pdo = null;
        $sauvegarde = Chores\sauvegarder_base($base);

        // Un nouveau lien après la sauvegarde : c'est lui qui doit rester en service après la restauration.
        $pdo = Chores\connexion($base);
        $jeton = (string) Chores\nouveau_jeton($pdo);
        $pdo = null;

        // Base actuelle sans tables (recréée vide par une visite) : la restauration passe quand même.
        foreach (['', '-wal', '-shm'] as $suffixe) {
            @unlink($base . $suffixe);
        }
        Chores\connexion($base)->exec('SELECT 1');
        $r = Chores\restaurer_base($sauvegarde, $base);
        self::assertSame(9, $r['cousins']);
        self::assertFalse($r['lienGarde'], 'une base vide n’a pas de lien à garder');
        self::assertFileDoesNotExist($this->dossier . '/stockage/' . Chores\TEMOIN_MAINTENANCE);

        // Base actuelle abîmée : copiée telle quelle à côté, puis remplacée.
        file_put_contents($base, "SQLite format 3\0" . str_repeat("\x07", 4096));
        $r = Chores\restaurer_base($sauvegarde, $base);
        self::assertFileExists((string) $r['remplacee']);
        self::assertSame('Démo', Chores\connexion($base)->query("SELECT titre FROM chansons WHERE slug = 'demo'")->fetchColumn());

        // Base actuelle saine, avec un lien plus récent que la sauvegarde : le lien en service est gardé.
        $pdo = Chores\connexion($base);
        Chores\nouveau_jeton($pdo);
        $jeton = (string) Chores\nouveau_jeton($pdo);
        $version = Chores\version_lien($pdo);
        $pdo = null;
        $r = Chores\restaurer_base($sauvegarde, $base);
        self::assertTrue($r['lienGarde']);
        $pdo = Chores\connexion($base);
        self::assertTrue(Chores\jeton_valide($pdo, $jeton));
        self::assertSame($version, Chores\version_lien($pdo));
        self::assertFalse(Chores\en_maintenance($base));
    }

    public function testTemoinDeMaintenanceUneSeuleRestaurationEtOubliApresDixMinutes(): void
    {
        $base = $this->dossier . '/stockage/base.sqlite';
        Chores\migrer(Chores\connexion($base));
        $sauvegarde = Chores\sauvegarder_base($base);
        $temoin = $this->dossier . '/stockage/' . Chores\TEMOIN_MAINTENANCE;

        // Témoin posé (restauration en cours ailleurs, ou maintenance à la main) : une seconde restauration est refusée.
        file_put_contents($temoin, '');
        self::assertTrue(Chores\en_maintenance($base));
        try {
            Chores\restaurer_base($sauvegarde, $base);
            self::fail('deux restaurations à la fois');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('déjà en cours', $e->getMessage());
        }
        self::assertFileExists($temoin, 'le témoin d’un autre n’est pas retiré');

        // Témoin vide (posé à la main) : jamais oublié. Témoin automatique de plus de 10 minutes : oublié.
        touch($temoin, time() - 3600);
        self::assertTrue(Chores\en_maintenance($base));
        file_put_contents($temoin, Chores\MARQUE_TEMOIN_AUTOMATIQUE . "\n");
        touch($temoin, time() - 3600);
        self::assertFalse(Chores\en_maintenance($base));
        touch($temoin, time() - 60);
        self::assertTrue(Chores\en_maintenance($base));
    }

    public function testLaRouteDeRestaurationRefuseUnNomHorsDeLaListe(): void
    {
        [$cookies, $champs] = $this->formulaire();
        foreach (['../base.sqlite', 'base-2026-10-13-120000.sqlite/../../config.php', 'autre.sqlite', ''] as $nom) {
            $r = $this->traiter($this->contexteAdmin('POST', '/admin/restaurer', $cookies, $champs + ['sauvegarde' => $nom]));
            self::assertSame(303, $r->statut, $nom);
            self::assertSame('/admin/base?erreur=inconnue', $r->entetes['Location'], $nom);
        }
        // Sans jeton CSRF : refusé ; sans cookie d'administration : 404.
        self::assertSame(400, $this->traiter($this->contexteAdmin('POST', '/admin/restaurer', [], ['sauvegarde' => 'x']))->statut);
        self::assertSame(404, $this->traiter($this->contexte('POST', '/admin/restaurer', $cookies, $champs + ['sauvegarde' => 'x']))->statut);
    }

    public function testListeEtElagageDesSauvegardesParBase(): void
    {
        $dossier = $this->dossier . '/sauvegardes';
        mkdir($dossier);
        foreach (['base-2026-10-01-040000.sqlite', 'base-2026-10-08-040000.sqlite', 'base-2026-10-08-040000-2.sqlite', 'test-2026-10-13-120000.sqlite', 'autre.sqlite'] as $nom) {
            file_put_contents($dossier . '/' . $nom, 'x');
        }
        // Une sauvegarde renvoyée depuis le PC (date de fichier récente) garde sa place : le tri suit le nom.
        touch($dossier . '/base-2026-10-01-040000.sqlite', time() + 60);
        $noms = array_column(Chores\liste_sauvegardes($dossier, 'base'), 'nom');
        self::assertSame(['base-2026-10-08-040000-2.sqlite', 'base-2026-10-08-040000.sqlite', 'base-2026-10-01-040000.sqlite'], $noms);
        self::assertSame(['test-2026-10-13-120000.sqlite'], array_column(Chores\liste_sauvegardes($dossier, 'test'), 'nom'));
        self::assertSame('08/10/2026 à 04:00', Chores\date_sauvegarde('base-2026-10-08-040000-2.sqlite'));
        self::assertFalse(Chores\nom_sauvegarde_valide('../base.sqlite', 'base'));
        self::assertFalse(Chores\nom_sauvegarde_valide('base-2026-10-08-040000.sqlite/../x', 'base'));

        self::assertSame(1, Chores\elaguer_sauvegardes($dossier, 'base', 2));
        self::assertFileDoesNotExist($dossier . '/base-2026-10-01-040000.sqlite');
        self::assertFileExists($dossier . '/test-2026-10-13-120000.sqlite');
        self::assertFileExists($dossier . '/autre.sqlite');
    }

    public function testFaitsDuGroupeTrouvesDansLeDepotPuisDansLePaquet(): void
    {
        self::assertNotNull(Chores\fichiers_des_faits());
        mkdir($this->dossier . '/donnees');
        self::assertNull(Chores\fichiers_des_faits($this->dossier));
        file_put_contents($this->dossier . '/donnees/etat.json', '{}');
        file_put_contents($this->dossier . '/donnees/distribution.md', '');
        self::assertSame([$this->dossier . '/donnees/etat.json', $this->dossier . '/donnees/distribution.md'], Chores\fichiers_des_faits($this->dossier));
    }

    public function testLeJournalEtLeDebitNeGardentAucuneAdresse(): void
    {
        $pdo = Chores\connexion(':memory:');
        [$cookies, $champs] = $this->formulaire();
        // Installation (refus puis succès), faux lien, bon lien, prénom, Ma page : tout ce qui écrit au journal.
        Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => 'faux']), Chores\routes());
        $installe = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/installer', $cookies, $champs + ['cle' => self::CLE_ADMIN]), Chores\routes());
        preg_match('#/chores/e/([A-Za-z0-9_-]{43})#', $installe->corps, $m);
        Chores\traiter($this->contexteSur($pdo, 'GET', '/chores/e/' . str_repeat('B', 43)), Chores\routes());
        $entree = Chores\traiter($this->contexteSur($pdo, 'GET', '/chores/e/' . $m[1]), Chores\routes());
        $acces = $this->cookiesPoses($entree)['cm_acces'];
        $prenom = Chores\traiter($this->contexteSur($pdo, 'POST', '/chores/prenom', $cookies + ['cm_acces' => $acces], $champs + ['cousin' => '5']), Chores\routes());
        self::assertSame(303, $prenom->statut);

        $lignes = [];
        foreach (['journal', 'debit'] as $table) {
            foreach ($pdo->query("SELECT * FROM {$table}")->fetchAll() as $ligne) {
                $lignes[] = implode(' | ', array_map('strval', $ligne));
            }
        }
        $tout = implode("\n", $lignes);
        self::assertGreaterThanOrEqual(5, count($lignes));
        self::assertStringNotContainsString('198.51.100', $tout);
        self::assertDoesNotMatchRegularExpression('/\b\d{1,3}(\.\d{1,3}){3}\b/', $tout);
        self::assertDoesNotMatchRegularExpression('/\b[0-9a-f]{1,4}(:[0-9a-f]{0,4}){2,7}\b/i', preg_replace('/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\b/', '', $tout) ?? '');
    }
}
