<?php

declare(strict_types=1);

final class MigrationsTest extends CasDeBase
{
    public function testLesMigrationsSontIdempotentes(): void
    {
        self::assertSame([], Chores\migrer($this->pdo), 'deuxième passage');
        self::assertSame(9, (int) $this->pdo->query('SELECT COUNT(*) FROM cousins')->fetchColumn());
        self::assertSame(['001-socle', '002-donnees', '003-groupe'], $this->pdo->query('SELECT version FROM migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testLesNeufCousinsDansLOrdreAlphabetique(): void
    {
        $prenoms = array_column(Chores\cousins_actifs($this->pdo), 'prenom');

        self::assertSame(['Alex', 'Catherine', 'Fabien', 'Flobert', 'Jessica', 'Josiane', 'Nadine', 'Patrice', 'Stéphane'], $prenoms);
    }

    public function testPatriceSeulAdministrateurJessicaEtStephaneHorsDuGroupe(): void
    {
        self::assertSame(['Patrice'], $this->pdo->query('SELECT prenom FROM cousins WHERE est_admin = 1')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(
            ['Jessica', 'Stéphane'],
            $this->pdo->query('SELECT prenom FROM cousins WHERE dans_le_groupe_whatsapp = 0 ORDER BY ordre')->fetchAll(PDO::FETCH_COLUMN)
        );
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cousins WHERE contact_whatsapp IS NOT NULL')->fetchColumn());
    }

    public function testLeJournalNeSeModifiePasEtSePurgeAUnAn(): void
    {
        Chores\journaliser($this->pdo, 'entree', null, 'test');
        try {
            $this->pdo->exec("UPDATE journal SET detail = 'autre'");
            self::fail('Le journal a été modifié.');
        } catch (PDOException $erreur) {
            self::assertStringContainsString('ne se modifie pas', $erreur->getMessage());
        }

        $this->pdo->prepare('INSERT INTO journal (quand, type) VALUES (?, ?)')->execute([Chores\maintenant_utc(time() - 400 * 86400), 'entree']);
        self::assertSame(1, Chores\purger_journal($this->pdo));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM journal')->fetchColumn());
    }

    public function testLeJournalRefuseUnTypeInconnuEtCoupeLeDetail(): void
    {
        Chores\journaliser($this->pdo, 'prenom', 1, str_repeat('é', 300));
        self::assertSame(200, mb_strlen((string) $this->pdo->query('SELECT detail FROM journal')->fetchColumn()));

        $this->expectException(InvalidArgumentException::class);
        Chores\journaliser($this->pdo, 'inconnu');
    }

    public function testUneMigrationEnErreurNeLaissePasDeTrace(): void
    {
        $dossier = sys_get_temp_dir() . '/chores-migrations-' . bin2hex(random_bytes(4));
        mkdir($dossier);
        file_put_contents($dossier . '/001-socle.sql', '-- déjà appliquée');
        file_put_contents($dossier . '/002-cassee.sql', 'CREATE TABLE essai (id INTEGER); SELECT * FROM table_absente;');
        try {
            Chores\migrer($this->pdo, $dossier);
            self::fail('La migration cassée est passée.');
        } catch (RuntimeException $erreur) {
            self::assertStringContainsString('002-cassee', $erreur->getMessage());
        } finally {
            array_map('unlink', glob($dossier . '/*.sql') ?: []);
            rmdir($dossier);
        }
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'essai'")->fetchColumn());
        self::assertSame(['001-socle', '002-donnees', '003-groupe'], $this->pdo->query('SELECT version FROM migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
    }
}
