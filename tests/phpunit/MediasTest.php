<?php

declare(strict_types=1);

/**
 * Vidéos de référence (spec § 7) et flux /m/{id} : contrôle du cookie, plages d'octets, signature, refus HEVC sur
 * des fixtures de quelques octets fabriquées ici (aucun binaire versionné, aucune vidéo réelle).
 */
final class MediasTest extends CasDonnees
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = sys_get_temp_dir() . '/chores-medias-' . bin2hex(random_bytes(4));
        mkdir($this->dossier);
        Chores\dossier_medias($this->dossier);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dossier . '/*') ?: []);
        @rmdir($this->dossier);
        Chores\dossier_medias(Chores\RACINE . '/stockage/medias');
        parent::tearDown();
    }

    /** Fichier temporaire « reçu » : en-tête ftyp (MP4) ou EBML (WebM), un atome de codec, du remplissage. */
    private function fixture(string $sorte, string $codec = 'avc1'): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'chores-fixture-');
        $octets = match ($sorte) {
            'mp4' => "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . str_repeat("\x00", 64)
                // Atome stsd : version et drapeaux, une entrée, taille de l'entrée, puis son type (le codec).
                . 'stsd' . "\x00\x00\x00\x00" . "\x00\x00\x00\x01" . "\x00\x00\x00\x56" . $codec . str_repeat("\x01", 512),
            'webm' => "\x1A\x45\xDF\xA3" . str_repeat("\x02", 600),
            default => 'Ceci n’est pas une vidéo.' . str_repeat(' ', 600),
        };
        file_put_contents($chemin, $octets);

        return $chemin;
    }

    /** @return array<string, array<string, mixed>> */
    private function fichiers(string $chemin, string $nom, string $type, int $erreur = UPLOAD_ERR_OK): array
    {
        return ['video' => ['name' => $nom, 'type' => $type, 'tmp_name' => $chemin, 'error' => $erreur, 'size' => (int) filesize($chemin)]];
    }

    public function testDetectionDesFormatsEtDuCodec(): void
    {
        self::assertSame('video/mp4', Chores\type_par_signature($this->fixture('mp4')));
        self::assertSame('video/webm', Chores\type_par_signature($this->fixture('webm')));
        self::assertNull(Chores\type_par_signature($this->fixture('texte')));
        self::assertSame('h264', Chores\deviner_codec($this->fixture('mp4', 'avc1')));
        self::assertSame('hevc', Chores\deviner_codec($this->fixture('mp4', 'hvc1')));
        self::assertSame('hevc', Chores\deviner_codec($this->fixture('mp4', 'hev1')));
        self::assertSame('inconnu', Chores\deviner_codec($this->fixture('webm')));
        // Quatre lettres « hvc1 » hors de l'atome stsd (dans l'image, par hasard) ne font pas un HEVC.
        $hasard = $this->fixture('mp4', 'avc1');
        file_put_contents($hasard, 'hvc1hev1', FILE_APPEND);
        self::assertSame('h264', Chores\deviner_codec($hasard));
    }

    public function testUneReferenceHevcEstRefuseeEnToutesLettres(): void
    {
        $r = $this->envoyer('/admin/chansons/kanimaa/reference', null, [], [], true, $this->fichiers($this->fixture('mp4', 'hvc1'), 'kanimaa.mp4', 'video/mp4'));

        self::assertSame(422, $r->statut);
        self::assertStringContainsString('Cette vidéo est en HEVC', $r->corps);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn());
        self::assertSame([], glob($this->dossier . '/*'));
    }

    public function testFichiersRefuses(): void
    {
        $cas = [
            [$this->fixture('texte'), 'faux.mp4', 'video/mp4', UPLOAD_ERR_OK, 'n’est pas une vidéo acceptée'],
            [$this->fixture('mp4'), 'video.mov', 'video/quicktime', UPLOAD_ERR_OK, 'n’est pas une vidéo acceptée'],
            [$this->fixture('webm'), 'video.mp4', 'video/mp4', UPLOAD_ERR_OK, 'n’est pas une vidéo acceptée'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, 'trop lourde : 200 Mo au plus'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_NO_FILE, 'Aucun fichier reçu'],
        ];
        foreach ($cas as [$chemin, $nom, $type, $erreur, $attendu]) {
            $r = $this->envoyer('/admin/chansons/kanimaa/reference', null, [], [], true, $this->fichiers($chemin, $nom, $type, $erreur));
            self::assertSame(422, $r->statut, $nom);
            self::assertStringContainsString($attendu, $r->corps, $nom);
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn());
    }

    public function testDeposerServirRemplacerRetirer(): void
    {
        $r = $this->envoyer('/admin/chansons/kanimaa/reference', null, [], [], true, $this->fichiers($this->fixture('mp4'), 'Kanimaa final.mp4', 'video/mp4'));
        self::assertSame(303, $r->statut);
        $video = $this->pdo->query('SELECT * FROM videos')->fetch();
        self::assertSame(['reference', 'video/mp4', 'h264'], [$video['type'], $video['mime'], $video['codec_devine']]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.mp4$/', $video['fichier'], 'nom interne aléatoire, jamais celui d’origine');
        self::assertFileExists($this->dossier . '/' . $video['fichier']);
        self::assertSame((int) $video['id'], (int) $this->chanson('kanimaa')['video_ref_id']);
        self::assertStringContainsString('src="/m/' . $video['id'] . '?v=' . substr($video['fichier'], 0, 8) . '"', $this->obtenir('/chanson/kanimaa', 'Jessica')->corps);

        // Flux : cookie d'accès exigé, plages d'octets, type de la base, cache privé.
        $sansCookie = $this->traiter(new Chores\Contexte($this->config(), 'GET', '/m/' . $video['id'], [], [], '192.0.2.10', '', '127.0.0.1:8095', false, $this->pdo));
        self::assertSame(302, $sansCookie->statut);
        self::assertSame('/lien', $sansCookie->entetes['Location']);
        $entier = $this->obtenir('/m/' . $video['id'], null);
        self::assertSame(200, $entier->statut);
        self::assertSame('video/mp4', $entier->entetes['Content-Type']);
        self::assertSame('private, max-age=31536000, immutable', $entier->entetes['Cache-Control']);
        $plage = $this->obtenir('/m/' . $video['id'], null, [], ['range' => 'bytes=0-99']);
        self::assertSame(206, $plage->statut);
        self::assertSame('bytes 0-99/' . $video['taille_o'], $plage->entetes['Content-Range']);
        self::assertSame('100', $plage->entetes['Content-Length']);
        self::assertSame(404, $this->obtenir('/m/9999', null)->statut);
        self::assertSame(404, $this->obtenir('/m/abc', null)->statut);

        // Remplacer : l'ancienne est marquée supprimée et son fichier effacé ; WebM accepté pour les essais.
        $this->envoyer('/admin/chansons/kanimaa/reference', null, [], [], true, $this->fichiers($this->fixture('webm'), 'essai.webm', 'video/webm'));
        self::assertFileDoesNotExist($this->dossier . '/' . $video['fichier']);
        self::assertSame(404, $this->obtenir('/m/' . $video['id'], null)->statut);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM videos WHERE supprime_le IS NULL')->fetchColumn());

        $this->envoyer('/admin/chansons/kanimaa/reference', null, ['action' => 'retirer'], [], true);
        self::assertNull($this->chanson('kanimaa')['video_ref_id']);
        self::assertSame([], glob($this->dossier . '/*'));
        self::assertStringContainsString('Pas encore de vidéo de référence', $this->obtenir('/chanson/kanimaa', 'Jessica')->corps);
    }

    public function testPlusDePlace(): void
    {
        $this->pdo->exec("INSERT INTO videos (chanson_id, type, titre, fichier, mime, taille_o, cree_le) VALUES (1, 'reference', 'Démo', '" . str_repeat('a', 32) . ".mp4', 'video/mp4', " . (5000 * 1024 * 1024) . ", '2026-10-13T00:00:00Z')");
        $r = $this->envoyer('/admin/chansons/kanimaa/reference', null, [], [], true, $this->fichiers($this->fixture('mp4'), 'k.mp4', 'video/mp4'));
        self::assertSame(422, $r->statut);
        self::assertStringContainsString('Plus de place', $r->corps);
    }

    public function testEnvoiTropLourdPourPhpDitLaTaille(): void
    {
        $c = new Chores\Contexte(
            $this->config(), 'POST', '/admin/chansons/kanimaa/reference', $this->cookiesDe(null, true), [], '192.0.2.10', '', '127.0.0.1:8095', false,
            $this->pdo, 'same-origin', [], ['content-length' => '900000000', 'content-type' => 'multipart/form-data; boundary=x']
        );
        $r = $this->traiter($c);
        self::assertSame(413, $r->statut);
        self::assertStringContainsString('200 Mo au plus', $r->corps);
    }
}
