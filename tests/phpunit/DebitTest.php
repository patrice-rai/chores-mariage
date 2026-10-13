<?php

declare(strict_types=1);

final class DebitTest extends CasDeBase
{
    private const IP = '203.0.113.42';

    public function testLOnziemeEssaiDansLaMinuteEstRefuse(): void
    {
        $t = gmmktime(10, 15, 5, 10, 13, 2026);
        for ($i = 1; $i <= 10; $i++) {
            self::assertTrue(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $t), "essai {$i}");
        }
        self::assertFalse(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $t), '11e essai');
        self::assertTrue(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', '203.0.113.43', null, $t), 'autre adresse');
        self::assertTrue(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $t + 60), 'minute suivante');
    }

    public function testSoixanteEssaisParHeure(): void
    {
        $debut = gmmktime(10, 0, 0, 10, 13, 2026);
        for ($i = 0; $i < 60; $i++) {
            self::assertTrue(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $debut + $i * 60));
        }
        self::assertFalse(Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $debut + 3599));
    }

    public function testVingtChoixDePrenomParHeure(): void
    {
        $t = gmmktime(10, 0, 0, 10, 13, 2026);
        for ($i = 0; $i < 20; $i++) {
            self::assertTrue(Chores\debit_autorise($this->pdo, self::SECRET, 'prenom', self::IP, null, $t + $i));
        }
        self::assertFalse(Chores\debit_autorise($this->pdo, self::SECRET, 'prenom', self::IP, null, $t + 30));
    }

    public function testLaCleNeContientPasLAdresseEnClairEtChangeChaqueJour(): void
    {
        $t = gmmktime(10, 0, 0, 10, 13, 2026);
        Chores\debit_autorise($this->pdo, self::SECRET, 'entree', self::IP, null, $t);

        $lignes = $this->pdo->query('SELECT cle, fenetre FROM debit')->fetchAll();
        self::assertNotEmpty($lignes);
        foreach ($lignes as $ligne) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $ligne['cle']);
            self::assertStringNotContainsString(self::IP, implode('|', $ligne));
        }
        self::assertNotSame(
            Chores\cle_debit(self::SECRET, 'entree', self::IP, $t),
            Chores\cle_debit(self::SECRET, 'entree', self::IP, $t + 86400)
        );
    }

    public function testUneAdresseIpv6CompteParPrefixeDe64(): void
    {
        $t = gmmktime(10, 0, 0, 10, 13, 2026);
        self::assertSame(
            Chores\cle_debit(self::SECRET, 'entree', '2001:db8:1:2::1', $t),
            Chores\cle_debit(self::SECRET, 'entree', '2001:db8:1:2:ffff::9', $t)
        );
        self::assertNotSame(
            Chores\cle_debit(self::SECRET, 'entree', '2001:db8:1:2::1', $t),
            Chores\cle_debit(self::SECRET, 'entree', '2001:db8:1:3::1', $t)
        );
        // Une adresse IPv4 écrite en IPv6 garde son propre compteur.
        self::assertSame('203.0.113.7', Chores\reseau_ip('::ffff:203.0.113.7'));
        self::assertNotSame(
            Chores\cle_debit(self::SECRET, 'entree', '::ffff:203.0.113.7', $t),
            Chores\cle_debit(self::SECRET, 'entree', '::ffff:203.0.113.8', $t)
        );
    }

    public function testPurgeDesVieillesFenetres(): void
    {
        $t = gmmktime(10, 0, 0, 10, 13, 2026);
        $ajout = $this->pdo->prepare('INSERT INTO debit (cle, fenetre, compteur) VALUES (?, ?, 1)');
        $ajout->execute([str_repeat('a', 64), gmdate('Y-m-d\TH:i', $t - 5 * 86400)]);
        $ajout->execute([str_repeat('a', 64), gmdate('Y-m-d\TH', $t - 5 * 86400)]);
        $ajout->execute([str_repeat('a', 64), gmdate('Y-m-d\TH:i', $t)]);

        self::assertSame(2, Chores\purger_debit($this->pdo, $t));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM debit')->fetchColumn());
    }

    public function testLeRefusRepondEn429EnFrancaisEtSeJournaliseSansAdresse(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Chores\debit_autorise($this->pdo, self::SECRET, 'entree', '192.0.2.10');
        }
        $r = $this->traiter($this->contexte('GET', '/e/' . str_repeat('A', 43)));

        self::assertSame(429, $r->statut);
        self::assertSame('60', $r->entetes['Retry-After']);
        self::assertStringContainsString('Réessaie dans une minute', $r->corps);
        $journal = $this->pdo->query("SELECT * FROM journal WHERE type = 'refus_debit'")->fetchAll();
        self::assertCount(1, $journal);
        self::assertStringNotContainsString('192.0.2.10', (string) json_encode($journal));
    }
}
