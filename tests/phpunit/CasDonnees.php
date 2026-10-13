<?php

declare(strict_types=1);

/**
 * Base des tests de la phase 4 : base en mémoire migrée, faits du groupe importés (docs/whatsapp/etat.json et
 * docs/distribution.md), un lien en service ; requêtes au nom d'un cousin, avec ou sans cookie d'administration.
 */
abstract class CasDonnees extends CasDeBase
{
    protected function setUp(): void
    {
        parent::setUp();
        Chores\importer_etat($this->pdo, self::etatJson(), self::distributionMd());
        Chores\nouveau_jeton($this->pdo);
    }

    protected static function etatJson(): string
    {
        return (string) file_get_contents(Chores\RACINE . '/docs/whatsapp/etat.json');
    }

    protected static function distributionMd(): string
    {
        return (string) file_get_contents(Chores\RACINE . '/docs/distribution.md');
    }

    protected function idCousin(string $prenom): int
    {
        $requete = $this->pdo->prepare('SELECT id FROM cousins WHERE prenom = ?');
        $requete->execute([$prenom]);

        return (int) $requete->fetchColumn();
    }

    /** @return array<string, mixed> */
    protected function chanson(string $slug): array
    {
        return Chores\chanson_par_slug($this->pdo, $slug) ?? throw new RuntimeException("Chanson absente : {$slug}");
    }

    /** @return array<string, string> cookies d'accès, de prénom (si donné), d'administration (si demandé) et CSRF */
    protected function cookiesDe(?string $prenom, bool $admin = false): array
    {
        $version = Chores\version_lien($this->pdo);
        $cookies = ['cm_acces' => Chores\valeur_cookie_acces(self::SECRET, $version), 'cm_csrf' => str_repeat('A', 43)];
        if ($prenom !== null) {
            $cookies['cm_prenom'] = Chores\valeur_cookie_prenom(self::SECRET, $this->idCousin($prenom), $version);
        }
        if ($admin) {
            $cookies['cm_admin'] = Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN);
        }

        return $cookies;
    }

    /**
     * @param array<string, string> $requete
     * @param array<string, string> $entetes
     */
    protected function obtenir(string $chemin, ?string $prenom, array $requete = [], array $entetes = [], bool $admin = false): Chores\Reponse
    {
        return $this->traiter(new Chores\Contexte(
            $this->config(), 'GET', $chemin, $this->cookiesDe($prenom, $admin), [], '192.0.2.10', '', '127.0.0.1:8095', false,
            $this->pdo, 'same-origin', $requete, $entetes
        ));
    }

    /**
     * @param array<string, string> $champs
     * @param array<string, string> $entetes
     * @param array<string, mixed> $fichiers
     */
    protected function envoyer(string $chemin, ?string $prenom, array $champs, array $entetes = [], bool $admin = false, array $fichiers = []): Chores\Reponse
    {
        $c = new Chores\Contexte(
            $this->config(), 'POST', $chemin, $this->cookiesDe($prenom, $admin),
            $champs + ['csrf' => Chores\champ_csrf_attendu(self::SECRET, str_repeat('A', 43))],
            '192.0.2.10', '', '127.0.0.1:8095', false, $this->pdo, 'same-origin', [], $entetes, $fichiers
        );
        $c->televersementSimule = true;

        return $this->traiter($c);
    }

    protected function ajouterSection(string $slug, string $nom, float $debut, float $fin, int $comptes = 2): int
    {
        $chanson = $this->chanson($slug);
        $ordre = (int) $this->pdo->query('SELECT COUNT(*) FROM sections WHERE chanson_id = ' . (int) $chanson['id'])->fetchColumn() + 1;
        $this->pdo->prepare('INSERT INTO sections (chanson_id, ordre, nom, debut_s, fin_s, comptes) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([(int) $chanson['id'], $ordre, $nom, $debut, $fin, $comptes]);

        return (int) $this->pdo->lastInsertId();
    }
}
