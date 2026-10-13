<?php

declare(strict_types=1);

/** Import des faits du groupe (spec § 5) : contenu exact, idempotence, sources, rien d'inventé. */
final class ImportTest extends CasDonnees
{
    public function testLImportRelanceDeuxFoisNeCreeAucunDoublon(): void
    {
        $avant = $this->instantane();
        $compte = Chores\importer_etat($this->pdo, self::etatJson(), self::distributionMd());

        self::assertSame(0, $compte['chansons'] + $compte['liens'] + $compte['repetitions'] + $compte['presences'] + $compte['checklist']);
        self::assertSame($avant, $this->instantane());
    }

    public function testContenuExact(): void
    {
        $n = fn (string $table): int => (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        self::assertSame(9, $n('cousins'));
        self::assertSame(8, $n('chansons'));
        self::assertSame(1, $n('repetitions'));
        self::assertSame(0, $n('sections'));
        self::assertSame(0, $n('placements'));
        self::assertSame(0, $n('videos'));
        self::assertSame(0, $n('niveaux'));
        self::assertSame(23, $n('liens'));
        self::assertSame(15, (int) $this->pdo->query("SELECT COUNT(*) FROM liens WHERE type = 'tutoriel'")->fetchColumn());

        self::assertSame(
            ['Celebration of Varisu', 'Innisai', 'Kanimaa', 'Maamadura', 'Sangeeth Celebration', 'Thalapathy Kacheri', 'The Wedding Song', 'Yaaro Yaarodi'],
            $this->pdo->query('SELECT titre FROM chansons ORDER BY titre')->fetchAll(PDO::FETCH_COLUMN)
        );
        self::assertSame(['Innisai'], $this->pdo->query("SELECT titre FROM chansons WHERE statut = 'decidee'")->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM chansons WHERE ordre_passage IS NOT NULL OR createur_id IS NOT NULL OR video_ref_id IS NOT NULL')->fetchColumn());

        // Distribution d'après distribution.md.
        $danseurs = fn (string $slug, string $statut = 'danse'): array => array_column(array_filter(
            Chores\distribution($this->pdo, (int) $this->chanson($slug)['id']),
            static fn (array $d): bool => $d['statut'] === $statut
        ), 'prenom');
        $huit = ['Alex', 'Catherine', 'Fabien', 'Flobert', 'Jessica', 'Josiane', 'Patrice', 'Stéphane'];
        self::assertSame($huit, $danseurs('celebration-of-varisu'));
        self::assertSame($huit, $danseurs('kanimaa'));
        self::assertSame(['Catherine', 'Jessica', 'Josiane'], $danseurs('yaaro-yaarodi'));
        self::assertSame(['Alex', 'Fabien', 'Flobert'], $danseurs('thalapathy-kacheri'));
        self::assertSame(['Patrice'], $danseurs('thalapathy-kacheri', 'en_attente'));
        self::assertSame(['Nadine'], $danseurs('innisai'));
        self::assertSame([], $danseurs('maamadura'));

        // Durées et tempos sourcés ; seuls 140 est vérifié ; Innisai sans durée ni film.
        $varisu = $this->chanson('celebration-of-varisu');
        self::assertSame([168, 140, 1], [(int) $varisu['duree_s'], (int) $varisu['bpm'], (int) $varisu['bpm_verifie']]);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM chansons WHERE bpm_verifie = 1 AND slug <> 'celebration-of-varisu'")->fetchColumn());
        $innisai = $this->chanson('innisai');
        self::assertNull($innisai['duree_s']);
        self::assertNull($innisai['film']);
        self::assertSame(1, (int) $innisai['a_creer']);
        self::assertSame(1, (int) $this->chanson('yaaro-yaarodi')['a_creer']);
        // Aucune valeur de démonstration de la maquette (BPM 96/128/72/112).
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM chansons WHERE bpm IN (96, 128, 72, 112)')->fetchColumn());

        // Répétitions et réponses.
        $reponses = $this->pdo->query(
            'SELECT r.date, r.statut, c.prenom, p.reponse FROM presences p JOIN repetitions r ON r.id = p.repetition_id JOIN cousins c ON c.id = p.cousin_id ORDER BY r.date, c.ordre'
        )->fetchAll(PDO::FETCH_NUM);
        // Dépôt public : aucune réponse du groupe n'est versionnée (etat.json ne porte que la date et le lieu).
        self::assertSame([], $reponses);
        self::assertSame('Villiers', $this->pdo->query("SELECT lieu FROM repetitions WHERE date = '2026-09-06'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM repetitions WHERE compte_rendu IS NOT NULL')->fetchColumn());

        self::assertSame(['Tenue', 'Enceinte', 'Musiques coupées à la bonne durée'], $this->pdo->query('SELECT libelle FROM checklist ORDER BY ordre')->fetchAll(PDO::FETCH_COLUMN));
        self::assertNull($this->pdo->query("SELECT contact_whatsapp FROM cousins WHERE prenom = 'Nadine'")->fetchColumn(), 'surnoms WhatsApp non versionnés');
        self::assertSame([], json_decode((string) Chores\reglage($this->pdo, 'questions_ouvertes'), true));
        foreach (['date_mariage', 'lieu_mariage', 'lien_sondage', 'placements_verrouilles'] as $cle) {
            self::assertNull(Chores\reglage($this->pdo, $cle), $cle);
        }
    }

    public function testChaqueFaitImporteFigureDansSaSource(): void
    {
        $recherches = (string) file_get_contents(Chores\RACINE . '/docs/recherches/chansons-et-tutoriels-2026-10-12.md');
        foreach (Chores\TUTORIELS as [$slug, $url, $titre, $duree, $miroir]) {
            $ligne = $this->ligneContenant($recherches, $url);
            self::assertNotNull($ligne, "tutoriel absent de recherches/ : {$url}");
            self::assertStringContainsString('tutoriel', $ligne, $url);
            self::assertStringContainsString($duree, $ligne, $url);
            if ($miroir === 1) {
                self::assertStringContainsString('miroir', $ligne, $url);
            }
        }
        foreach (array_keys(Chores\LIENS_PARTAGES) as $url) {
            self::assertStringContainsString($url, self::etatJson());
        }
        foreach (['2:48', '5:47', '3:17', '4:04', '140 BPM', '131 (', '~142 BPM', '~97', '~145 BPM', 'Alaipayuthey', 'Jana Nayagan', 'Retro'] as $fait) {
            self::assertStringContainsString($fait, $recherches, $fait);
        }
    }

    public function testUnTempoCorrigeParPatriceNEstPasEcraseParUnNouvelImport(): void
    {
        $this->pdo->exec("UPDATE chansons SET bpm = 150, bpm_verifie = 1 WHERE slug = 'kanimaa'");
        $this->pdo->exec("UPDATE checklist SET libelle = 'Tenues' WHERE libelle = 'Tenue'");
        Chores\importer_etat($this->pdo, self::etatJson(), self::distributionMd());

        self::assertSame(150, (int) $this->chanson('kanimaa')['bpm']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM checklist')->fetchColumn());
    }

    public function testCeQuePatriceARetireNEstPasRemisParUnNouvelImport(): void
    {
        $kacheri = (int) $this->chanson('thalapathy-kacheri')['id'];
        $this->pdo->exec("DELETE FROM distributions WHERE chanson_id = {$kacheri} AND statut = 'en_attente'");
        $this->pdo->exec("DELETE FROM liens WHERE url = 'https://youtu.be/Sr5hYJ7vzlM'");
        $this->pdo->exec("DELETE FROM presences WHERE cousin_id = (SELECT id FROM cousins WHERE prenom = 'Patrice')");
        Chores\importer_etat($this->pdo, self::etatJson(), self::distributionMd());

        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM distributions WHERE chanson_id = {$kacheri} AND statut = 'en_attente'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM liens WHERE url = 'https://youtu.be/Sr5hYJ7vzlM'")->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM presences')->fetchColumn(), 'aucune réponse versionnée');
    }

    public function testUnFaitNouveauDesSourcesMetLeStatutAJour(): void
    {
        // Patrice passe de « en attente » à « danse » sur Thalapathy Kacheri dans les deux sources.
        $etat = str_replace('"danseurs": ["Fabien", "Flobert", "Alex"], "danseurs_en_attente": ["Patrice"]', '"danseurs": ["Fabien", "Flobert", "Alex", "Patrice"]', self::etatJson());
        $distribution = str_replace('Fabien, Flobert, Alex — **Patrice en attente**', 'Fabien, Flobert, Alex, Patrice', self::distributionMd());
        self::assertNotSame(self::etatJson(), $etat);
        self::assertNotSame(self::distributionMd(), $distribution);
        Chores\importer_etat($this->pdo, $etat, $distribution);

        $statut = $this->pdo->query("SELECT d.statut FROM distributions d JOIN chansons ch ON ch.id = d.chanson_id JOIN cousins c ON c.id = d.cousin_id WHERE ch.slug = 'thalapathy-kacheri' AND c.prenom = 'Patrice'")->fetchColumn();
        self::assertSame('danse', $statut);
    }

    public function testUneChansonDansanteAbsenteDeDistributionMdArreteLImport(): void
    {
        $pdo = Chores\connexion(':memory:');
        Chores\migrer($pdo);
        $distribution = (string) preg_replace('/^\| 2 \| Yaaro Yaarodi .*$/m', '', self::distributionMd());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Yaaro Yaarodi');
        Chores\importer_etat($pdo, self::etatJson(), $distribution);
    }

    public function testUnMauvaisFichierDeDistributionArreteLImport(): void
    {
        foreach (['# Rien ici', "| 1 | Chanson inconnue | Alex | 1 | rien |"] as $faux) {
            $pdo = Chores\connexion(':memory:');
            Chores\migrer($pdo);
            try {
                Chores\importer_etat($pdo, self::etatJson(), $faux);
                self::fail('Le contrôle croisé s’est tu.');
            } catch (RuntimeException $erreur) {
                self::assertStringContainsString('distribution.md', $erreur->getMessage());
            }
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM chansons')->fetchColumn());
        }
    }

    public function testDesSourcesQuiSeContredisentNeLaissentRien(): void
    {
        $pdo = Chores\connexion(':memory:');
        Chores\migrer($pdo);
        $etat = str_replace('"danseurs": ["Josiane", "Catherine", "Jessica"]', '"danseurs": ["Josiane", "Catherine"]', self::etatJson());
        try {
            Chores\importer_etat($pdo, $etat, self::distributionMd());
            self::fail('La contradiction est passée.');
        } catch (RuntimeException $erreur) {
            self::assertStringContainsString('Yaaro Yaarodi', $erreur->getMessage());
        }
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM chansons')->fetchColumn());
    }

    public function testUnPrenomInconnuEstRefuse(): void
    {
        $pdo = Chores\connexion(':memory:');
        Chores\migrer($pdo);
        $etat = str_replace('"titre": "Maamadura", "danseurs": []', '"titre": "Maamadura", "danseurs": ["Inconnue"]', self::etatJson());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prénom inconnu');
        Chores\importer_etat($pdo, $etat, self::distributionMd());
    }

    public function testSlugsEtTemps(): void
    {
        self::assertSame('celebration-of-varisu', Chores\slug('Celebration of Varisu'));
        self::assertSame('the-wedding-song', Chores\slug('The Wedding Song'));
        self::assertSame(727, Chores\secondes_depuis_mmss('12:07'));
        self::assertNull(Chores\secondes_depuis_mmss('douze'));
    }

    private function ligneContenant(string $texte, string $url): ?string
    {
        $id = substr($url, strrpos($url, '/') + 1);
        foreach (preg_split('/\R/', $texte) ?: [] as $ligne) {
            if (str_contains($ligne, '(https://youtu.be/' . $id . ')')) {
                return $ligne;
            }
        }

        return null;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function instantane(): array
    {
        $tables = ['cousins', 'chansons', 'liens', 'distributions', 'repetitions', 'presences', 'checklist'];
        $etat = [];
        foreach ($tables as $table) {
            $etat[$table] = $this->pdo->query("SELECT * FROM {$table} ORDER BY 1, 2")->fetchAll();
        }

        return $etat;
    }
}
