<?php

declare(strict_types=1);

final class JetonTest extends CasDeBase
{
    public function testUnNouveauJetonEstLongEtSeulSonEmpreinteEstGardee(): void
    {
        $jeton = Chores\nouveau_jeton($this->pdo);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $jeton);
        self::assertSame(hash('sha256', $jeton), Chores\reglage($this->pdo, 'jeton_hash'));
        $tout = json_encode($this->pdo->query('SELECT * FROM reglages')->fetchAll());
        self::assertStringNotContainsString($jeton, (string) $tout);
        self::assertSame(1, Chores\version_lien($this->pdo));
    }

    public function testLeBonJetonEstAccepte(): void
    {
        $jeton = Chores\nouveau_jeton($this->pdo);

        self::assertTrue(Chores\jeton_valide($this->pdo, $jeton));
    }

    public function testUnFauxJetonEstRefuse(): void
    {
        $jeton = Chores\nouveau_jeton($this->pdo);
        $faux = substr($jeton, 0, -1) . ($jeton[42] === 'A' ? 'B' : 'A');

        self::assertFalse(Chores\jeton_valide($this->pdo, $faux));
        self::assertFalse(Chores\jeton_valide($this->pdo, ''));
        self::assertFalse(Chores\jeton_valide($this->pdo, $jeton . 'x'));
        self::assertFalse(Chores\jeton_valide($this->pdo, '../../etc/passwd'));
    }

    public function testSansLienPersonneNEntre(): void
    {
        self::assertFalse(Chores\jeton_valide($this->pdo, str_repeat('A', 43)));
        self::assertSame(0, Chores\version_lien($this->pdo));
    }

    public function testUnJetonRevoqueEstRefuseEtLaVersionAvance(): void
    {
        $ancien = Chores\nouveau_jeton($this->pdo);
        $nouveau = Chores\nouveau_jeton($this->pdo);

        self::assertFalse(Chores\jeton_valide($this->pdo, $ancien));
        self::assertTrue(Chores\jeton_valide($this->pdo, $nouveau));
        self::assertSame(2, Chores\version_lien($this->pdo));
    }

    public function testLeLienCompletSuitLaBaseEtLAdressePublique(): void
    {
        $config = $this->config('/chores', 'production');
        self::assertSame('https://exemple.test/chores/e/abc', Chores\lien_complet($config, 'abc', 'exemple.test'));
        self::assertSame('https://[domaine]/chores/e/abc', Chores\lien_complet($config, 'abc', 'mauvais hôte<'));
        $config['URL_PUBLIQUE'] = 'https://cousins.test';
        self::assertSame('https://cousins.test/chores/e/abc', Chores\lien_complet($config, 'abc', 'autre.test'));
    }
}
