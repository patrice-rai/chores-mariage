<?php

declare(strict_types=1);

/**
 * Vidéos des cousins (spec § 7) : téléversement (signature, taille, HEVC, quota, débit), commentaires horodatés,
 * suppression (fichier effacé, droits), durée, remplacement par Patrice. Fixtures de quelques octets fabriquées ici,
 * jamais versionnées.
 */
final class VideosTest extends CasDonnees
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = sys_get_temp_dir() . '/chores-videos-' . bin2hex(random_bytes(4));
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

    private function fixture(string $sorte, string $codec = 'avc1'): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'chores-fixture-');
        $octets = match ($sorte) {
            'mp4' => "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . str_repeat("\x00", 64)
                . 'stsd' . "\x00\x00\x00\x00" . "\x00\x00\x00\x01" . "\x00\x00\x00\x56" . $codec . str_repeat("\x01", 512),
            'mov' => "\x00\x00\x00\x14ftypqt  \x00\x00\x00\x00qt  " . str_repeat("\x00", 64)
                . 'stsd' . "\x00\x00\x00\x00" . "\x00\x00\x00\x01" . "\x00\x00\x00\x56" . $codec . str_repeat("\x01", 512),
            'webm' => "\x1A\x45\xDF\xA3" . str_repeat("\x02", 600),
            default => 'Ceci n’est pas une vidéo.' . str_repeat(' ', 600),
        };
        file_put_contents($chemin, $octets);

        return $chemin;
    }

    /** @return array<string, array<string, mixed>> */
    private function fichier(string $chemin, string $nom, string $type, int $erreur = UPLOAD_ERR_OK): array
    {
        return ['video' => ['name' => $nom, 'type' => $type, 'tmp_name' => $chemin, 'error' => $erreur, 'size' => (int) filesize($chemin)]];
    }

    private function poster(string $prenom, array $fichiers, array $champs = [], array $entetes = []): Chores\Reponse
    {
        return $this->envoyer('/videos', $prenom, $champs + ['chanson' => 'kanimaa', 'titre' => 'Section A de test', 'note' => 'Note de test'], $entetes, false, $fichiers);
    }

    private function deposer(string $prenom = 'Jessica', string $codec = 'avc1'): array
    {
        $r = $this->poster($prenom, $this->fichier($this->fixture('mp4', $codec), 'IMG_0001.mp4', 'video/mp4'));
        self::assertSame(303, $r->statut, $r->corps);

        return $this->pdo->query('SELECT * FROM videos ORDER BY id DESC LIMIT 1')->fetch();
    }

    public function testTeleversementValide(): void
    {
        $video = $this->deposer();
        self::assertSame(['cousin', 'video/mp4', 'h264', 'Section A de test', 'Note de test'], [$video['type'], $video['mime'], $video['codec_devine'], $video['titre'], $video['note']]);
        self::assertSame($this->idCousin('Jessica'), (int) $video['cousin_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.mp4$/', $video['fichier']);
        self::assertFileExists($this->dossier . '/' . $video['fichier']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'video_ajout'")->fetchColumn());

        $page = $this->obtenir('/videos/' . $video['id'], 'Alex', ['ok' => 'ajoutee']);
        self::assertSame(200, $page->statut);
        self::assertStringContainsString('src="/m/' . $video['id'] . '?v=' . substr($video['fichier'], 0, 8) . '"', $page->corps);
        self::assertStringNotContainsString('Supprimer ma vidéo', $page->corps, 'un autre cousin ne supprime pas');

        $album = $this->obtenir('/videos', 'Alex')->corps;
        self::assertStringContainsString('Jessica · Section A de test', $album);
        self::assertStringContainsString('1 vidéo', $album);
        self::assertStringContainsString('Jessica · Section A de test', $this->obtenir('/chanson/kanimaa', 'Alex')->corps);
        self::assertStringContainsString('Jessica a posté « Section A de test »', $this->obtenir('/moi', 'Alex')->corps);

        // MOV d'iPhone en H.264 (« Le plus compatible ») et WebM d'Android : acceptés.
        self::assertSame(303, $this->poster('Alex', $this->fichier($this->fixture('mov'), 'IMG_0002.MOV', 'video/quicktime'))->statut);
        self::assertSame(303, $this->poster('Alex', $this->fichier($this->fixture('webm'), 'essai.webm', 'video/webm'))->statut);
    }

    public function testFichiersRefusesEnToutesLettres(): void
    {
        $cas = [
            [$this->fixture('texte'), 'faux.mp4', 'video/mp4', UPLOAD_ERR_OK, 'Ce fichier n’est pas une vidéo acceptée : envoie un fichier MP4, MOV, WEBM.'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, 'La vidéo est trop lourde : 200 Mo au plus.'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_FORM_SIZE, 'La vidéo est trop lourde : 200 Mo au plus.'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_PARTIAL, 'L’envoi s’est interrompu'],
            [$this->fixture('mp4'), 'video.mp4', 'video/mp4', UPLOAD_ERR_NO_FILE, 'Aucun fichier reçu'],
        ];
        foreach ($cas as [$chemin, $nom, $type, $erreur, $message]) {
            $r = $this->poster('Jessica', $this->fichier($chemin, $nom, $type, $erreur));
            self::assertSame(422, $r->statut);
            self::assertStringContainsString($message, $r->corps);
            self::assertStringContainsString('Section A de test', $r->corps, 'aucune ressaisie du titre');
            self::assertStringContainsString('Choisis de nouveau le fichier', $r->corps);
        }
        $champs = $this->poster('Jessica', $this->fichier($this->fixture('mp4'), 'v.mp4', 'video/mp4'), ['chanson' => 'inconnue', 'titre' => '', 'note' => str_repeat('n', 301)]);
        self::assertSame(422, $champs->statut);
        foreach (['Choisis la chanson de ta vidéo.', 'Donne un titre à ta vidéo', 'La note dépasse 300 caractères.'] as $message) {
            self::assertStringContainsString($message, $champs->corps);
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn());
        self::assertSame([], glob($this->dossier . '/*'));

        $json = $this->poster('Jessica', $this->fichier($this->fixture('texte'), 'faux.mp4', 'video/mp4'), [], ['accept' => 'application/json']);
        self::assertSame(422, $json->statut);
        self::assertSame(['erreurs' => ['video-fichier' => 'Ce fichier n’est pas une vidéo acceptée : envoie un fichier MP4, MOV, WEBM.']], json_decode($json->corps, true));
    }

    public function testHevcAccepteAvecAvertissement(): void
    {
        $video = $this->deposer('Jessica', 'hvc1');
        self::assertSame('hevc', $video['codec_devine']);
        self::assertStringContainsString('Ta vidéo est peut-être en HEVC', $this->obtenir('/videos/' . $video['id'], 'Jessica')->corps);
        $autre = $this->obtenir('/videos/' . $video['id'], 'Alex')->corps;
        self::assertStringNotContainsString('Ta vidéo est peut-être en HEVC', $autre);
        self::assertStringContainsString('format iPhone', $autre);
        self::assertStringContainsString('format iPhone', $this->obtenir('/videos', 'Alex')->corps);
    }

    public function testQuotaDisque(): void
    {
        $this->pdo->prepare("INSERT INTO videos (chanson_id, type, titre, fichier, mime, taille_o, cree_le) VALUES (?, 'reference', 'Démo', ?, 'video/mp4', ?, '2026-10-13T00:00:00Z')")
            ->execute([(int) $this->chanson('kanimaa')['id'], str_repeat('a', 32) . '.mp4', 5000 * 1024 * 1024]);
        $r = $this->poster('Jessica', $this->fichier($this->fixture('mp4'), 'v.mp4', 'video/mp4'));
        self::assertSame(422, $r->statut);
        self::assertStringContainsString('Plus de place : demande à Patrice de faire du ménage.', $r->corps);
    }

    public function testDebitDesTeleversementsCompteLesEnvoisValides(): void
    {
        // Un fichier refusé ne fait pas perdre d'essai.
        $this->poster('Jessica', $this->fichier($this->fixture('texte'), 'faux.mp4', 'video/mp4'));
        for ($i = 1; $i <= 5; ++$i) {
            self::assertSame(303, $this->poster('Jessica', $this->fichier($this->fixture('mp4'), "v{$i}.mp4", 'video/mp4'))->statut, "envoi {$i}");
        }
        $sixieme = $this->poster('Jessica', $this->fichier($this->fixture('mp4'), 'v6.mp4', 'video/mp4'));
        self::assertSame(429, $sixieme->statut);
        self::assertStringContainsString('Tu as déjà envoyé beaucoup de vidéos', $sixieme->corps);
        // Les refus ne consomment rien : le compteur du jour reste à 5 (sinon réessayer viderait le quota du jour).
        for ($i = 0; $i < 5; ++$i) {
            $this->poster('Jessica', $this->fichier($this->fixture('mp4'), "r{$i}.mp4", 'video/mp4'));
        }
        $jour = $this->pdo->prepare('SELECT compteur FROM debit WHERE cle = ? AND fenetre = ?');
        $jour->execute([Chores\cle_debit(self::SECRET, 'video', 'cousin-' . $this->idCousin('Jessica'), time()), gmdate('Y-m-d')]);
        self::assertSame(5, (int) $jour->fetchColumn());
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn());
        self::assertCount(5, glob($this->dossier . '/*') ?: [], 'le fichier refusé n’est pas gardé');
        // Un autre cousin peut encore envoyer (même adresse : 10 par heure).
        self::assertSame(303, $this->poster('Alex', $this->fichier($this->fixture('mp4'), 'a.mp4', 'video/mp4'))->statut);
    }

    public function testCommentairesHorodatesEtBornes(): void
    {
        $video = $this->deposer();
        $id = $video['id'];
        $r = $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['temps' => '0:03', 'texte' => 'Bras plus haut ici']);
        self::assertSame(303, $r->statut);
        self::assertMatchesRegularExpression("#^/videos/{$id}\\?ok=commentaire&t=3\\#commentaire-\\d+$#", $r->entetes['Location']);
        // Deux commentaires au même instant : les deux sont gardés, dans l'ordre d'écriture.
        $this->envoyer("/videos/{$id}/commentaires", 'Nadine', ['temps' => '0:03', 'texte' => 'Et le pied gauche']);
        $this->envoyer("/videos/{$id}/commentaires", 'Josiane', ['temps' => '', 'texte' => 'Bravo pour toute la vidéo']);
        $this->envoyer("/videos/{$id}/commentaires", 'Fabien', ['temps' => '0:01,5', 'texte' => 'Départ']);
        $liste = Chores\commentaires_de($this->pdo, (int) $id);
        self::assertSame(['Fabien', 'Alex', 'Nadine', 'Josiane'], array_column($liste, 'auteur'));
        self::assertSame([1.5, 3.0, 3.0, null], array_column($liste, 'temps'));

        $page = $this->obtenir("/videos/{$id}", 'Alex')->corps;
        self::assertStringContainsString('href="/videos/' . $id . '?t=3#lecteur-video" data-temps="3">à 0:03', $page);
        self::assertSame(1, substr_count($page, 'Supprimer<span class="visuellement-cache"> le commentaire'), 'Alex ne supprime que le sien');

        foreach ([['3 min', 'texte', 'L’instant est illisible'], ['', '', 'Écris ton commentaire'], ['', str_repeat('x', 201), 'Écris ton commentaire']] as [$temps, $texte, $message]) {
            $refus = $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['temps' => $temps, 'texte' => $texte]);
            self::assertSame(422, $refus->statut);
            self::assertStringContainsString($message, $refus->corps);
        }
        $this->pdo->exec("UPDATE videos SET duree_s = 20 WHERE id = {$id}");
        self::assertStringContainsString('L’instant dépasse la durée de la vidéo (0:20).', $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['temps' => '0:42', 'texte' => 'Trop loin'])->corps);
    }

    public function testTrenteCommentairesParHeure(): void
    {
        $id = $this->deposer()['id'];
        for ($i = 1; $i <= 30; ++$i) {
            $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['texte' => "Commentaire {$i}"]);
        }
        $refus = $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['texte' => 'Un de trop']);
        self::assertSame(429, $refus->statut);
        self::assertSame(30, (int) $this->pdo->query("SELECT COUNT(*) FROM commentaires WHERE video_id = {$id}")->fetchColumn());
    }

    public function testSuppressionParLAuteurOuPatriceEffaceLeFichier(): void
    {
        $video = $this->deposer();
        $id = $video['id'];
        self::assertSame(403, $this->envoyer("/videos/{$id}/supprimer", 'Alex', [])->statut);
        // N'importe qui peut choisir le prénom « Patrice » : il faut la clé d'administration.
        self::assertSame(403, $this->envoyer("/videos/{$id}/supprimer", 'Patrice', [])->statut);
        self::assertFileExists($this->dossier . '/' . $video['fichier']);
        self::assertStringContainsString('Supprimer « Section A de test » ?', $this->obtenir("/videos/{$id}", 'Jessica', ['supprimer' => '1'])->corps);

        $r = $this->envoyer("/videos/{$id}/supprimer", 'Jessica', []);
        self::assertSame(303, $r->statut);
        self::assertSame('/videos?ok=supprimee', $r->entetes['Location']);
        self::assertFileDoesNotExist($this->dossier . '/' . $video['fichier']);
        self::assertNotNull($this->pdo->query("SELECT supprime_le FROM videos WHERE id = {$id}")->fetchColumn());
        self::assertSame(404, $this->obtenir("/videos/{$id}", 'Jessica')->statut);
        self::assertSame(404, $this->obtenir("/m/{$id}", 'Jessica')->statut);

        $autre = $this->deposer('Alex');
        $c = new Chores\Contexte(
            $this->config(), 'POST', '/videos/' . $autre['id'] . '/supprimer', $this->cookiesDe('Nadine', true),
            ['csrf' => Chores\champ_csrf_attendu(self::SECRET, str_repeat('A', 43))], '192.0.2.10', '', '127.0.0.1:8095', false, $this->pdo, 'same-origin'
        );
        self::assertSame(303, $this->traiter($c)->statut, 'Patrice (clé d’administration) supprime');
    }

    public function testSuppressionDUnCommentaire(): void
    {
        $id = $this->deposer()['id'];
        $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['temps' => '0:02', 'texte' => 'À retirer']);
        $commentaire = Chores\commentaires_de($this->pdo, (int) $id)[0]['id'];
        self::assertSame(403, $this->envoyer("/videos/{$id}/commentaires/{$commentaire}/supprimer", 'Jessica', [])->statut);
        self::assertStringContainsString('Supprimer ce commentaire d’Alex ?', $this->obtenir("/videos/{$id}", 'Alex', ['supprimer-commentaire' => (string) $commentaire])->corps);
        self::assertSame(303, $this->envoyer("/videos/{$id}/commentaires/{$commentaire}/supprimer", 'Alex', [])->statut);
        self::assertSame([], Chores\commentaires_de($this->pdo, (int) $id));
    }

    public function testDureePoseeUneFoisParLAuteur(): void
    {
        $id = $this->deposer()['id'];
        self::assertSame(403, $this->envoyer("/videos/{$id}/duree", 'Alex', ['duree' => '12.5'], ['accept' => 'application/json'])->statut);
        self::assertSame(422, $this->envoyer("/videos/{$id}/duree", 'Jessica', ['duree' => '-3'], ['accept' => 'application/json'])->statut);
        self::assertSame(200, $this->envoyer("/videos/{$id}/duree", 'Jessica', ['duree' => '20.25'], ['accept' => 'application/json'])->statut);
        $this->envoyer("/videos/{$id}/duree", 'Jessica', ['duree' => '99'], ['accept' => 'application/json']);
        self::assertSame(20.25, (float) $this->pdo->query("SELECT duree_s FROM videos WHERE id = {$id}")->fetchColumn());
    }

    public function testRemplacementParUneVersionConvertie(): void
    {
        $video = $this->deposer('Jessica', 'hvc1');
        $id = $video['id'];
        $this->envoyer("/videos/{$id}/commentaires", 'Alex', ['temps' => '0:02', 'texte' => 'Gardé après remplacement']);
        $liste = $this->obtenir('/admin/videos', null, [], [], true);
        self::assertSame(200, $liste->statut);
        self::assertStringContainsString('format HEVC', $liste->corps);
        self::assertSame(404, $this->obtenir('/admin/videos', 'Patrice')->statut);

        $hevc = $this->envoyer("/admin/videos/{$id}/remplacer", null, [], [], true, $this->fichier($this->fixture('mp4', 'hev1'), 'encore.mp4', 'video/mp4'));
        self::assertSame(422, $hevc->statut);
        self::assertStringContainsString('Cette version est encore en HEVC', $hevc->corps);

        $r = $this->envoyer("/admin/videos/{$id}/remplacer", null, [], [], true, $this->fichier($this->fixture('mp4', 'avc1'), 'convertie.mp4', 'video/mp4'));
        self::assertSame(303, $r->statut);
        $apres = $this->pdo->query("SELECT * FROM videos WHERE id = {$id}")->fetch();
        self::assertSame('h264', $apres['codec_devine']);
        self::assertNotSame($video['fichier'], $apres['fichier']);
        self::assertFileDoesNotExist($this->dossier . '/' . $video['fichier']);
        self::assertFileExists($this->dossier . '/' . $apres['fichier']);
        self::assertCount(1, Chores\commentaires_de($this->pdo, (int) $id));
        self::assertStringContainsString('?v=' . substr($apres['fichier'], 0, 8), $this->obtenir("/videos/{$id}", 'Alex')->corps);
        // L'ancienne adresse ne sert plus rien : une copie gardée sur un téléphone s'efface sur ce 404.
        self::assertSame(404, $this->obtenir("/m/{$id}", 'Alex', ['v' => substr($video['fichier'], 0, 8)])->statut);
        self::assertSame(200, $this->obtenir("/m/{$id}", 'Alex', ['v' => substr($apres['fichier'], 0, 8)])->statut);

        self::assertSame(303, $this->envoyer("/admin/videos/{$id}/supprimer", null, [], [], true)->statut);
        self::assertFileDoesNotExist($this->dossier . '/' . $apres['fichier']);
    }
}
