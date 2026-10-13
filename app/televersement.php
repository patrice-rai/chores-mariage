<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Téléversement des vidéos (spec § 7) : extension, type annoncé et signature du fichier vérifiés, taille et espace
// bornés, codec deviné (H.264 ou HEVC) sans rien convertir ni exécuter, nom interne aléatoire hors racine web.
// Phase 4 : vidéos de référence déposées par Patrice ; phase 5 : vidéos des cousins (mêmes fonctions).

/** Types acceptés → extensions. */
const TYPES_VIDEO = [
    'video/mp4' => ['mp4', 'm4v'],
    'video/quicktime' => ['mov'],
    'video/webm' => ['webm'],
];

/** Octets lus au début et à la fin du fichier pour deviner le codec (le « moov » peut être à la fin). */
const OCTETS_CODEC = 4 * 1024 * 1024;

/** Type d'après la signature : « ftyp » aux octets 4 à 8 (MP4, MOV), 1A 45 DF A3 (WebM) ; null sinon. */
function type_par_signature(string $chemin): ?string
{
    $flux = @fopen($chemin, 'rb');
    if ($flux === false) {
        return null;
    }
    $tete = (string) fread($flux, 12);
    fclose($flux);
    if (strlen($tete) >= 12 && substr($tete, 4, 4) === 'ftyp') {
        return substr($tete, 8, 4) === 'qt  ' ? 'video/quicktime' : 'video/mp4';
    }
    if (str_starts_with($tete, "\x1A\x45\xDF\xA3")) {
        return 'video/webm';
    }

    return null;
}

/** « hevc » si un atome hvc1/hev1 apparaît, « h264 » si avc1, « inconnu » sinon (début et fin du fichier). */
function deviner_codec(string $chemin): string
{
    $taille = (int) @filesize($chemin);
    $flux = @fopen($chemin, 'rb');
    if ($flux === false) {
        return 'inconnu';
    }
    $morceaux = [(string) fread($flux, OCTETS_CODEC)];
    if ($taille > OCTETS_CODEC) {
        fseek($flux, max(OCTETS_CODEC, $taille - OCTETS_CODEC));
        $morceaux[] = (string) fread($flux, OCTETS_CODEC);
    }
    fclose($flux);
    // Le type de chaque piste est le premier « sample entry » de l'atome stsd : « stsd », version et drapeaux (4),
    // nombre d'entrées (4), taille de l'entrée (4), puis son type (4). On ne lit que là : quatre lettres qui
    // traîneraient ailleurs dans l'image ne comptent pas.
    $types = [];
    foreach ($morceaux as $octets) {
        $position = 0;
        while (($position = strpos($octets, 'stsd', $position)) !== false) {
            $types[] = substr($octets, $position + 16, 4);
            $position += 4;
        }
    }
    if (array_intersect($types, ['hvc1', 'hev1']) !== []) {
        return 'hevc';
    }
    if (array_intersect($types, ['avc1', 'avc3']) !== []) {
        return 'h264';
    }

    return 'inconnu';
}

/**
 * Contrôle un fichier reçu. Renvoie l'erreur en toutes lettres, ou le type, l'extension, la taille et le codec.
 * @param array<string, mixed>|null $fichier entrée de $_FILES
 * @param list<string> $typesPermis
 * @return array{erreur: string}|array{erreur: null, mime: string, extension: string, taille: int, codec: string, chemin: string}
 */
function controler_video(?array $fichier, int $maxOctets, array $typesPermis): array
{
    $maxMo = intdiv($maxOctets, 1024 * 1024);
    if ($fichier === null || !isset($fichier['error']) || is_array($fichier['error'])) {
        return ['erreur' => 'Aucun fichier reçu : choisis une vidéo.'];
    }
    $code = (int) $fichier['error'];
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return ['erreur' => "La vidéo est trop lourde : {$maxMo} Mo au plus."];
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['erreur' => 'Aucun fichier reçu : choisis une vidéo.'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['erreur' => 'L’envoi s’est interrompu : réessaie (avec le wifi, c’est plus sûr).'];
    }
    $taille = (int) ($fichier['size'] ?? 0);
    $chemin = (string) ($fichier['tmp_name'] ?? '');
    if ($taille <= 0 || $chemin === '' || !is_file($chemin)) {
        return ['erreur' => 'Le fichier reçu est vide : choisis une autre vidéo.'];
    }
    if ($taille > $maxOctets || (int) filesize($chemin) > $maxOctets) {
        return ['erreur' => "La vidéo est trop lourde : {$maxMo} Mo au plus."];
    }
    $extension = strtolower(pathinfo((string) ($fichier['name'] ?? ''), PATHINFO_EXTENSION));
    $annonce = strtolower(trim(explode(';', (string) ($fichier['type'] ?? ''))[0]));
    $formats = implode(', ', array_map(static fn (string $t): string => strtoupper(TYPES_VIDEO[$t][0]), $typesPermis));
    $refus = ['erreur' => "Ce fichier n’est pas une vidéo acceptée : envoie un fichier {$formats}."];
    if (!in_array($annonce, $typesPermis, true) || !in_array($extension, TYPES_VIDEO[$annonce] ?? [], true)) {
        return $refus;
    }
    $signature = type_par_signature($chemin);
    // Un .mp4 dont la marque dit « qt » reste lisible comme MP4 : on garde la famille annoncée si la signature est
    // de la même famille (ftyp), jamais un type sans signature.
    $famille = static fn (?string $t): string => $t === 'video/webm' ? 'webm' : ($t === null ? '' : 'iso');
    if ($signature === null || $famille($signature) !== $famille($annonce)) {
        return $refus;
    }

    return ['erreur' => null, 'mime' => $annonce, 'extension' => TYPES_VIDEO[$annonce][0], 'taille' => $taille, 'codec' => deviner_codec($chemin), 'chemin' => $chemin];
}

/** Range le fichier reçu sous un nom aléatoire dans le dossier des médias. @return string nom interne */
function ranger_video(Contexte $c, string $temporaire, string $extension): string
{
    $dossier = dossier_medias();
    if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
        throw new \RuntimeException('Dossier des vidéos impossible à créer.');
    }
    $nom = bin2hex(random_bytes(16)) . '.' . $extension;
    $ok = $c->televersementSimule ? rename($temporaire, $dossier . '/' . $nom) : move_uploaded_file($temporaire, $dossier . '/' . $nom);
    if (!$ok) {
        throw new \RuntimeException('Vidéo impossible à ranger.');
    }
    @chmod($dossier . '/' . $nom, 0640);

    return $nom;
}

/** Efface le fichier d'une vidéo (nom interne contrôlé : jamais de chemin). */
function effacer_fichier_video(string $fichier): void
{
    if (preg_match('/^[0-9a-f]{32}\.[a-z0-9]{3,4}$/D', $fichier) && is_file(dossier_medias() . '/' . $fichier)) {
        @unlink(dossier_medias() . '/' . $fichier);
    }
}

/** Plus de place si l'ajout dépasse ESPACE_MAX_MO. */
function espace_suffisant(PDO $pdo, array $config, int $ajout): bool
{
    return espace_videos($pdo) + $ajout <= $config['ESPACE_MAX_MO'] * 1024 * 1024;
}
