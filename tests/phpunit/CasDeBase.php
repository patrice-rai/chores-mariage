<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Base commune : SQLite en mémoire migrée et configuration de test (aucun secret réel). */
abstract class CasDeBase extends TestCase
{
    protected const SECRET = 'secret-de-test-0123456789abcdef-0123456789abcdef';
    protected const CLE_ADMIN = 'cle-admin-de-test-0123456789abcdefghijklmnop';

    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Chores\connexion(':memory:');
        Chores\migrer($this->pdo);
    }

    /** @return array<string, mixed> */
    protected function config(string $base = '', string $env = 'dev'): array
    {
        return Chores\normaliser_configuration([
            'SECRET' => self::SECRET,
            'CLE_ADMIN' => self::CLE_ADMIN,
            'BASE_PATH' => $base,
            'URL_PUBLIQUE' => '',
            'ENV' => $env,
        ]);
    }

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $post
     */
    protected function contexte(string $methode, string $chemin, array $cookies = [], array $post = [], string $base = '', string $origine = '', string $secFetchSite = ''): Chores\Contexte
    {
        return new Chores\Contexte(
            $this->config($base),
            $methode,
            Chores\retirer_base($chemin, Chores\normaliser_base($base)),
            $cookies,
            $post,
            '192.0.2.10',
            $origine,
            '127.0.0.1:8095',
            false,
            $this->pdo,
            $secFetchSite,
        );
    }

    protected function traiter(Chores\Contexte $c): Chores\Reponse
    {
        return Chores\traiter($c, Chores\routes());
    }

    /** @return array<string, string> cookies posés par la réponse (nom → valeur) */
    protected function cookiesPoses(Chores\Reponse $r): array
    {
        $poses = [];
        foreach ($r->cookies as $cookie) {
            $poses[$cookie['nom']] = $cookie['duree'] < 0 ? '' : $cookie['valeur'];
        }

        return $poses;
    }
}
