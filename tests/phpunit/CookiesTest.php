<?php

declare(strict_types=1);

final class CookiesTest extends CasDeBase
{
    public function testCookieDAccesValide(): void
    {
        $valeur = Chores\valeur_cookie_acces(self::SECRET, 3);

        self::assertTrue(Chores\cookie_acces_valide(self::SECRET, 3, $valeur));
    }

    public function testCookieDAccesAltereOuPerimeRefuse(): void
    {
        $valeur = Chores\valeur_cookie_acces(self::SECRET, 3);

        self::assertFalse(Chores\cookie_acces_valide(self::SECRET, 3, $valeur . 'x'));
        self::assertFalse(Chores\cookie_acces_valide(self::SECRET, 3, '3.' . str_repeat('A', 43)));
        self::assertFalse(Chores\cookie_acces_valide(self::SECRET, 4, $valeur), 'version précédente du lien');
        self::assertFalse(Chores\cookie_acces_valide('un-autre-secret-0123456789abcdef', 3, $valeur));
        self::assertFalse(Chores\cookie_acces_valide(self::SECRET, 0, Chores\valeur_cookie_acces(self::SECRET, 0)), 'aucun lien créé');
        self::assertFalse(Chores\cookie_acces_valide(self::SECRET, 3, null));
    }

    public function testCookiePrenomValideEtAltere(): void
    {
        $valeur = Chores\valeur_cookie_prenom(self::SECRET, 5, 2);

        self::assertSame(5, Chores\lire_cookie_prenom(self::SECRET, $valeur, 2));
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, $valeur, 3), 'lien renouvelé : le prénom est à rechoisir');
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, '6' . substr($valeur, 1), 2), 'identifiant changé');
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, substr($valeur, 0, -1) . 'x', 2));
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, '5', 2));
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, null, 2));
    }

    public function testUneSignatureNeSertPasDUnUsageALAutre(): void
    {
        // La signature d'accès de la version 5 ne vaut pas cookie prénom pour le cousin 5.
        self::assertNull(Chores\lire_cookie_prenom(self::SECRET, Chores\valeur_cookie_acces(self::SECRET, 5), 5));
        self::assertNotSame(Chores\signer(self::SECRET, 'acces', '5'), Chores\signer(self::SECRET, 'prenom', '5'));
    }

    public function testCookieAdministration(): void
    {
        $valeur = Chores\valeur_cookie_admin(self::SECRET, self::CLE_ADMIN);

        self::assertTrue(Chores\cookie_admin_valide(self::SECRET, self::CLE_ADMIN, $valeur));
        self::assertFalse(Chores\cookie_admin_valide(self::SECRET, self::CLE_ADMIN, $valeur . 'x'));
        self::assertFalse(Chores\cookie_admin_valide(self::SECRET, 'nouvelle-cle-0123456789abcdefghijklmnop', $valeur), 'clé changée');
        self::assertFalse(Chores\cookie_admin_valide(self::SECRET, '', $valeur), 'administration désactivée');
        self::assertStringNotContainsString(self::CLE_ADMIN, $valeur);
    }

    public function testCleAdministration(): void
    {
        self::assertTrue(Chores\cle_admin_valide(self::CLE_ADMIN, self::CLE_ADMIN));
        self::assertFalse(Chores\cle_admin_valide(self::CLE_ADMIN, self::CLE_ADMIN . 'x'));
        self::assertFalse(Chores\cle_admin_valide('', ''));
    }

    public function testAttributsDesCookies(): void
    {
        $dev = Chores\options_cookie($this->config(), Chores\DUREE_COOKIE);
        self::assertSame('/', $dev['path']);
        self::assertFalse($dev['secure']);
        self::assertTrue($dev['httponly']);
        self::assertSame('Lax', $dev['samesite']);
        self::assertGreaterThan(time() + 399 * 86400, $dev['expires']);

        $production = Chores\options_cookie($this->config('/chores', 'production'), 0);
        self::assertSame('/chores', $production['path']);
        self::assertTrue($production['secure']);
        self::assertSame(0, $production['expires']);
    }
}
