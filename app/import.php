<?php

declare(strict_types=1);

namespace Chores;

use PDO;

// Import des faits du groupe (spec § 5) : docs/whatsapp/etat.json (chansons, danseurs, liens, répétitions, votes,
// questions ouvertes) et docs/distribution.md (contrôle croisé des danseurs). Les quelques faits qui n'y sont pas
// (durées, films, tempos, tutoriels) viennent de docs/recherches/chansons-et-tutoriels-2026-10-12.md et sont tenus
// ci-dessous avec leur source ; OutilsImportTest vérifie que chaque URL et chaque durée figurent dans ces documents.
// Rien n'est inventé : sans source, la valeur reste NULL (affichée entre crochets).
//
// Idempotent et respectueux de l'administration : une chanson, un lien, une répétition ou une présence déjà en
// base n'est jamais réécrit (Patrice a pu corriger un tempo), et un fait déjà importé puis retiré par Patrice n'est
// jamais remis (registre `import_cles` des réglages) ; seuls les contacts WhatsApp des cousins et les questions
// ouvertes sont remis à jour. Aucune section, aucun placement, aucune vidéo.

/** Titres d'etat.json corrigés : « Inisai » est le titre YouTube « Innisai » (note d'etat.json). */
const TITRES_CORRIGES = ['Inisai' => 'Innisai'];

/**
 * Faits par chanson. Sources : recherches/ § 1 à 5 (durées, films, tempos ; « À créer » de la synthèse),
 * distribution.md (films des chansons proposées, « Nadine coupe la musique »).
 * Tempos : seul 140 (Varisu, songbpm + brief) est vérifié ; les autres sont « non vérifiés » dans recherches/.
 */
const FAITS_CHANSONS = [
    'celebration-of-varisu' => ['film' => 'Varisu (2023)', 'duree_s' => 168, 'bpm' => 140, 'bpm_verifie' => 1, 'a_creer' => 0, 'note' => null],
    'yaaro-yaarodi' => ['film' => 'Alaipayuthey (2000)', 'duree_s' => 347, 'bpm' => 131, 'bpm_verifie' => 0, 'a_creer' => 1, 'note' => null],
    'thalapathy-kacheri' => ['film' => 'Jana Nayagan (2026)', 'duree_s' => 197, 'bpm' => 142, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null],
    // Film à confirmer (Varalaru d'après le brief, Godfather d'après le lien de Nadine), durée inconnue.
    'innisai' => ['film' => null, 'duree_s' => null, 'bpm' => 97, 'bpm_verifie' => 0, 'a_creer' => 1, 'note' => 'Nadine coupe la musique.'],
    'kanimaa' => ['film' => 'Retro (2025)', 'duree_s' => 244, 'bpm' => 145, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null],
    'maamadura' => ['film' => 'Jigarthanda Double X', 'duree_s' => null, 'bpm' => null, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null],
    'the-wedding-song' => ['film' => 'Vishwanath and Sons', 'duree_s' => null, 'bpm' => null, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null],
    'sangeeth-celebration' => ['film' => 'Dragon', 'duree_s' => null, 'bpm' => null, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null],
];

/**
 * Liens partagés dans le groupe (etat.json) : titre en français. Qui l'a partagé et quand n'est pas versionné
 * (dépôt public) : [titre, partagé par, partagé le], les deux derniers à null.
 */
const LIENS_PARTAGES = [
    'https://youtu.be/Bsj8btRiKuE' => ['Danse de mariage de VrindhArjun', null, null],
    'https://youtu.be/lxTdrnJzZwY' => ['Innisai, audio (film Godfather, A.R. Rahman)', null, null],
    'https://youtu.be/6MeLi9KQD84' => ['Reprise dansée d’Ahinth Vijay ft. The Officials', null, null],
    'https://youtu.be/RI8o9iza7zE' => ['Reprise dansée d’AnuragerZ Choreo', null, null],
    'https://youtu.be/yl2FMetiMLw' => ['Maamadura ft. The Officials, Ahinth Vijay', null, null],
    'https://youtu.be/TTZ4bnxsv7A' => ['Reprise dansée du Studio J J', null, null],
    'https://youtu.be/rfza9kCybEs' => ['The Wedding Song, vidéo des paroles (Aditya Music Tamil)', null, null],
    'https://youtu.be/EUOafXrZjDQ' => ['Sangeeth Celebration (Think Music India)', null, null],
];

/**
 * Tutoriels de recherches/ : lignes dont le type contient « tutoriel », avec la durée de la ligne et « en miroir »
 * quand la ligne le dit (Mahir Shanz, Kala Samaya). [slug, url, titre, durée m:ss, en miroir (null : inconnu)].
 */
const TUTORIELS = [
    ['celebration-of-varisu', 'https://youtu.be/iCZQY8Q1Ye0', 'Gabriel Entertainment, pas à pas, chanson entière', '12:07', null],
    ['celebration-of-varisu', 'https://youtu.be/rZiX5Bus4BI', 'It’s DavidJames, partie 3 d’une série', '13:37', null],
    ['celebration-of-varisu', 'https://youtu.be/q_VqzqDjtk8', 'nk little dance (en tamoul)', '4:19', null],
    ['celebration-of-varisu', 'https://youtu.be/cwJL7vfZ1Ec', 'nk little dance (en tamoul), deuxième vidéo', '2:54', null],
    ['celebration-of-varisu', 'https://youtu.be/CCyjFJfRowU', 'Vijay Prabhakar, court tutoriel du refrain', '0:57', null],
    ['celebration-of-varisu', 'https://youtu.be/RTh__XHo4AA', 'Madras Cypher, court tutoriel du refrain', '0:59', null],
    ['thalapathy-kacheri', 'https://youtu.be/Sr5hYJ7vzlM', 'Mahir Shanz, vue miroir, pas à pas (malayalam)', '9:41', 1],
    ['thalapathy-kacheri', 'https://youtu.be/s1cvoY32KZU', 'Vrindharjun, tutoriel lent', '8:15', null],
    ['thalapathy-kacheri', 'https://youtu.be/mZ7Yf8y53c4', 'ASquare Crew, le pas du refrain (à deux)', '4:40', null],
    ['thalapathy-kacheri', 'https://youtu.be/0P524u2eFTo', 'Dance Marine', '13:59', null],
    ['thalapathy-kacheri', 'https://youtu.be/lZejAqKGw48', 'nk little dance, la fin', '8:23', null],
    ['kanimaa', 'https://youtu.be/VJEqJWeo_p0', 'Sherif Moves (chorégraphe du film), pour débutants', '5:49', null],
    ['kanimaa', 'https://youtu.be/cFha-3tRxvk', 'Kala Samaya, en miroir, pas à pas', '9:53', 1],
    ['kanimaa', 'https://youtu.be/mU7NQJl-ifg', 'Dance Marine, pas à pas', '9:00', null],
    ['kanimaa', 'https://youtu.be/uml5arKiWoE', 'Learn How To Dance, pas à pas', '4:05', null],
];

/** Notes des répétitions importées (aucune dans le dépôt public : les échanges du groupe ne sont pas versionnés). */
const NOTES_REPETITIONS = [];
const NOTE_SONDAGE = 'Date proposée dans le groupe WhatsApp (sondage).';
/** Horaires d'etat.json écrits en français courant. */
const HORAIRES = ['à partir de midi, jusqu\'à 19h' => 'de midi à 19 h'];

/** Les trois lignes de check-list du brief (jamais les cinq de la maquette). */
const CHECKLIST_INITIALE = ['Tenue', 'Enceinte', 'Musiques coupées à la bonne durée'];

/** « Celebration of Varisu » → « celebration-of-varisu ». */
function slug(string $titre): string
{
    $ascii = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titre));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');

    return $slug === '' ? 'chanson' : substr($slug, 0, 60);
}

/** « 12:07 » → 727 ; « 1:02:05 » → 3725 ; null si illisible. */
function secondes_depuis_mmss(string $texte): ?int
{
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})$/D', trim($texte), $m)) {
        return null;
    }

    return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
}

/** Date ISO avec fuseau (etat.json) → UTC de la base. */
function utc_depuis_iso(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return maintenant_utc();
    }
    try {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    } catch (\Exception) {
        return maintenant_utc();
    }
}

/**
 * Danseurs par chanson d'après le tableau de docs/distribution.md (« | 1 | Celebration of Varisu | Fabien, … | »).
 * @return array<string, array{danse: list<string>, en_attente: list<string>}> par titre
 */
function lire_distribution_md(string $markdown, array $prenoms): array
{
    $resultat = [];
    foreach (preg_split('/\R/', $markdown) ?: [] as $ligne) {
        if (!preg_match('/^\|\s*\d+\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|/u', $ligne, $m)) {
            continue;
        }
        $titre = trim((string) preg_replace('/\s*\(.*$/u', '', $m[1]));
        $danse = [];
        $attente = [];
        foreach ($prenoms as $prenom) {
            if (preg_match('/\*{0,2}' . preg_quote($prenom, '/') . '\s+en attente/u', $m[2])) {
                $attente[] = $prenom;
            } elseif (preg_match('/(^|[\s,])' . preg_quote($prenom, '/') . '($|[\s,—])/u', $m[2])) {
                $danse[] = $prenom;
            }
        }
        $resultat[$titre] = ['danse' => $danse, 'en_attente' => $attente];
    }

    return $resultat;
}

/**
 * Importe les faits. Lève une exception (et ne garde rien) si les deux sources se contredisent ou citent un
 * prénom inconnu.
 * @return array{cousins: int, chansons: int, liens: int, repetitions: int, presences: int, checklist: int}
 */
function importer_etat(PDO $pdo, string $etatJson, string $distributionMd): array
{
    $etat = json_decode($etatJson, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($etat) || !isset($etat['danseurs'], $etat['chansons'], $etat['repetitions'])) {
        throw new \RuntimeException('etat.json : danseurs, chansons et répétitions attendus.');
    }
    $ids = [];
    foreach ($pdo->query('SELECT id, prenom FROM cousins')->fetchAll() as $ligne) {
        $ids[(string) $ligne['prenom']] = (int) $ligne['id'];
    }
    $id = static function (string $prenom) use ($ids): int {
        return $ids[$prenom] ?? throw new \RuntimeException("Prénom inconnu dans les sources : {$prenom} (aucun ajout sans Patrice).");
    };
    $distribution = lire_distribution_md($distributionMd, array_keys($ids));
    // Le contrôle croisé ne doit jamais se taire : au moins une ligne reconnue, et chaque chanson du tableau de
    // distribution.md présente dans etat.json (un mauvais fichier ou un format changé arrête l'import).
    if ($distribution === []) {
        throw new \RuntimeException('distribution.md : aucun tableau de danseurs reconnu (mauvais fichier ?).');
    }
    $titresEtat = array_map(static fn (array $ch): string => TITRES_CORRIGES[(string) $ch['titre']] ?? (string) $ch['titre'], $etat['chansons']);
    foreach (array_keys($distribution) as $titre) {
        if (!in_array($titre, $titresEtat, true)) {
            throw new \RuntimeException("distribution.md cite « {$titre} », absente d'etat.json.");
        }
    }

    $compte = ['cousins' => 0, 'chansons' => 0, 'liens' => 0, 'repetitions' => 0, 'presences' => 0, 'checklist' => 0];
    $pdo->beginTransaction();
    try {
        // Registre des faits déjà importés : un fait que Patrice a retiré (danseur, lien, répétition, réponse) n'est
        // jamais remis par un nouvel import ; seul un fait nouveau des sources est ajouté.
        $cles = array_fill_keys(json_decode((string) (reglage($pdo, 'import_cles') ?? '[]'), true, 4, JSON_THROW_ON_ERROR) ?: [], true);
        $nouveau = static function (string $cle) use (&$cles): bool {
            if (isset($cles[$cle])) {
                return false;
            }
            $cles[$cle] = true;

            return true;
        };
        // Cousins : contacts WhatsApp et appartenance au groupe (jamais d'ajout).
        $majCousin = $pdo->prepare('UPDATE cousins SET contact_whatsapp = ?, dans_le_groupe_whatsapp = ? WHERE id = ?');
        foreach ($etat['danseurs'] as $d) {
            $majCousin->execute([$d['contact_whatsapp'] ?? null, !empty($d['dans_le_groupe']) ? 1 : 0, $id((string) $d['nom'])]);
            $compte['cousins'] += $majCousin->rowCount();
        }

        $chansonExiste = $pdo->prepare('SELECT id FROM chansons WHERE slug = ?');
        $ajoutChanson = $pdo->prepare(
            'INSERT INTO chansons (slug, titre, film, duree_s, bpm, bpm_verifie, statut, a_creer, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // Un fait nouveau des sources (« en attente » devenu « danse ») met le statut à jour.
        $ajoutDistribution = $pdo->prepare(
            'INSERT INTO distributions (chanson_id, cousin_id, statut) VALUES (?, ?, ?) ON CONFLICT(chanson_id, cousin_id) DO UPDATE SET statut = excluded.statut'
        );
        $ajoutLien = $pdo->prepare(
            'INSERT OR IGNORE INTO liens (chanson_id, url, titre, duree_s, type, en_miroir, partage_par, partage_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $idsChansons = [];
        foreach ($etat['chansons'] as $ch) {
            $titre = TITRES_CORRIGES[(string) $ch['titre']] ?? (string) $ch['titre'];
            $slug = slug($titre);
            $faits = FAITS_CHANSONS[$slug] ?? ['film' => null, 'duree_s' => null, 'bpm' => null, 'bpm_verifie' => 0, 'a_creer' => 0, 'note' => null];
            $danse = array_map('strval', $ch['danseurs'] ?? []);
            $attente = array_map('strval', $ch['danseurs_en_attente'] ?? []);

            // Contrôle croisé avec distribution.md pour les chansons qui y figurent.
            // Un prénom inconnu arrête tout, avant même le contrôle croisé (aucun ajout de cousin sans Patrice).
            array_map($id, array_merge($danse, $attente));
            // Dans l'autre sens aussi : une chanson qui a des danseurs dans etat.json doit être lue dans distribution.md.
            if (($danse !== [] || $attente !== []) && !isset($distribution[$titre])) {
                throw new \RuntimeException("distribution.md ne donne pas les danseurs de « {$titre} » (ligne absente ou illisible).");
            }
            if (isset($distribution[$titre])) {
                $a = $danse;
                $b = $distribution[$titre]['danse'];
                sort($a);
                sort($b);
                $c = $attente;
                $d = $distribution[$titre]['en_attente'];
                sort($c);
                sort($d);
                if ($a !== $b || $c !== $d) {
                    throw new \RuntimeException("etat.json et distribution.md ne donnent pas les mêmes danseurs pour {$titre}.");
                }
            }

            $chansonExiste->execute([$slug]);
            $chansonId = $chansonExiste->fetchColumn();
            if (!$nouveau('chanson:' . $slug) && $chansonId === false) {
                continue;
            }
            if ($chansonId === false) {
                $statut = in_array($ch['statut'] ?? '', ['proposee', 'decidee', 'ecartee'], true) ? $ch['statut'] : 'proposee';
                $ajoutChanson->execute([$slug, $titre, $faits['film'], $faits['duree_s'], $faits['bpm'], $faits['bpm_verifie'], $statut, $faits['a_creer'], $faits['note']]);
                $chansonId = (int) $pdo->lastInsertId();
                ++$compte['chansons'];
            }
            $chansonId = (int) $chansonId;
            $idsChansons[$slug] = $chansonId;
            foreach ([[$danse, 'danse'], [$attente, 'en_attente']] as [$prenoms, $statutDanseur]) {
                foreach ($prenoms as $prenom) {
                    $cousinId = $id($prenom);
                    // Le statut fait partie du fait : passer de « en attente » à « danse » dans les sources est nouveau.
                    if ($nouveau('danseur:' . $slug . ':' . $prenom . ':' . $statutDanseur)) {
                        $ajoutDistribution->execute([$chansonId, $cousinId, $statutDanseur]);
                    }
                }
            }
            foreach ($ch['liens'] ?? [] as $url) {
                $url = (string) $url;
                [$titreLien, $par, $le] = LIENS_PARTAGES[$url] ?? ['Lien partagé dans le groupe', null, null];
                if (!$nouveau('lien:' . $slug . ':' . $url)) {
                    continue;
                }
                $ajoutLien->execute([$chansonId, $url, $titreLien, null, 'reference', null, $par, $le]);
                $compte['liens'] += $ajoutLien->rowCount();
            }
        }
        foreach (TUTORIELS as [$slug, $url, $titreLien, $duree, $miroir]) {
            if (!isset($idsChansons[$slug]) || !$nouveau('lien:' . $slug . ':' . $url)) {
                continue;
            }
            $ajoutLien->execute([$idsChansons[$slug], $url, $titreLien, secondes_depuis_mmss($duree), 'tutoriel', $miroir, null, null]);
            $compte['liens'] += $ajoutLien->rowCount();
        }

        // Répétitions et réponses importées (source WhatsApp) : « presents » d'une répétition décidée = ont dit venir ;
        // d'une option du sondage = vote dispo (peut_etre).
        $repetitionExiste = $pdo->prepare('SELECT id FROM repetitions WHERE date = ?');
        $ajoutRepetition = $pdo->prepare('INSERT INTO repetitions (date, heure, lieu, statut, source, note) VALUES (?, ?, ?, ?, ?, ?)');
        $ajoutPresence = $pdo->prepare('INSERT OR IGNORE INTO presences (repetition_id, cousin_id, reponse, source, maj_le) VALUES (?, ?, ?, ?, ?)');
        foreach ($etat['repetitions'] as $r) {
            $date = (string) $r['date'];
            $statut = in_array($r['statut'] ?? '', ['proposee', 'decidee', 'passee', 'annulee'], true) ? $r['statut'] : 'proposee';
            $repetitionExiste->execute([$date]);
            $repetitionId = $repetitionExiste->fetchColumn();
            if (!$nouveau('repetition:' . $date) && $repetitionId === false) {
                continue;
            }
            if ($repetitionId === false) {
                $horaire = isset($r['horaire']) ? (HORAIRES[$r['horaire']] ?? (string) $r['horaire']) : null;
                $note = NOTES_REPETITIONS[$date] ?? ($statut === 'proposee' ? NOTE_SONDAGE : null);
                $ajoutRepetition->execute([$date, $horaire, $r['lieu'] ?? null, $statut, 'whatsapp', $note]);
                $repetitionId = (int) $pdo->lastInsertId();
                ++$compte['repetitions'];
            }
            $quand = utc_depuis_iso($r['dernier_message_source'] ?? null);
            $reponses = [
                [$r['presents'] ?? [], $statut === 'decidee' ? 'oui' : 'peut_etre'],
                [$r['incertains'] ?? [], 'peut_etre'],
                [$r['absents'] ?? [], 'non'],
            ];
            foreach ($reponses as [$prenoms, $reponse]) {
                foreach ($prenoms as $prenom) {
                    $cousinId = $id((string) $prenom);
                    if (!$nouveau('reponse:' . $date . ':' . $prenom)) {
                        continue;
                    }
                    $ajoutPresence->execute([(int) $repetitionId, $cousinId, $reponse, 'whatsapp', $quand]);
                    $compte['presences'] += $ajoutPresence->rowCount();
                }
            }
        }

        // Check-list du brief, semée une seule fois (Patrice peut ensuite renommer ou supprimer).
        if (reglage($pdo, 'import_checklist') === null) {
            $ajoutCheck = $pdo->prepare('INSERT INTO checklist (libelle, ordre) VALUES (?, ?)');
            foreach (CHECKLIST_INITIALE as $i => $libelle) {
                $ajoutCheck->execute([$libelle, $i + 1]);
                ++$compte['checklist'];
            }
            poser_reglage($pdo, 'import_checklist', maintenant_utc());
        }

        // Pense-bête de Patrice (/admin), jamais montré aux cousins.
        $questions = array_values(array_filter(array_map(
            static fn (array $q): string => (string) ($q['question'] ?? ''),
            $etat['questions_ouvertes'] ?? []
        )));
        poser_reglage($pdo, 'questions_ouvertes', json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        poser_reglage($pdo, 'import_cles', json_encode(array_keys($cles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        poser_reglage($pdo, 'import_le', maintenant_utc());
        $pdo->commit();
    } catch (\Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }

    return $compte;
}
