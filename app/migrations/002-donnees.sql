-- Données (phase 4, spec § 3) : chansons, liens, distributions, sections, placements, niveaux, répétitions,
-- présences, vidéos, commentaires, check-list. Aucune donnée semée ici : tout vient de scripts/importer-etat.php
-- (faits d'etat.json et de distribution.md) ou de l'administration de Patrice. Dates en texte ISO 8601 UTC.
-- Ni DROP, ni modification des tables de la phase 2.

CREATE TABLE videos (
    id INTEGER PRIMARY KEY,
    chanson_id INTEGER NOT NULL,
    cousin_id INTEGER REFERENCES cousins (id) ON DELETE SET NULL,
    type TEXT NOT NULL CHECK (type IN ('cousin', 'reference')),
    titre TEXT NOT NULL CHECK (length(titre) BETWEEN 1 AND 80),
    note TEXT CHECK (note IS NULL OR length(note) <= 300),
    -- Nom interne aléatoire (32 chiffres hexadécimaux + extension), jamais le nom d'origine ni un chemin.
    fichier TEXT NOT NULL UNIQUE CHECK (fichier GLOB '[0-9a-f]*.[a-z]*' AND fichier NOT GLOB '*[^0-9a-z.]*' AND length(fichier) BETWEEN 36 AND 37),
    mime TEXT NOT NULL CHECK (mime IN ('video/mp4', 'video/quicktime', 'video/webm')),
    taille_o INTEGER NOT NULL CHECK (taille_o >= 0),
    duree_s REAL CHECK (duree_s IS NULL OR duree_s >= 0),
    codec_devine TEXT NOT NULL DEFAULT 'inconnu' CHECK (codec_devine IN ('h264', 'hevc', 'inconnu')),
    en_miroir INTEGER NOT NULL DEFAULT 0 CHECK (en_miroir IN (0, 1)),
    cree_le TEXT NOT NULL,
    supprime_le TEXT,
    FOREIGN KEY (chanson_id) REFERENCES chansons (id)
);

CREATE INDEX videos_chanson ON videos (chanson_id, type);

CREATE TABLE chansons (
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE CHECK (slug GLOB '[a-z0-9]*' AND slug NOT GLOB '*[^a-z0-9-]*' AND length(slug) <= 60),
    titre TEXT NOT NULL UNIQUE CHECK (length(titre) BETWEEN 1 AND 80),
    film TEXT CHECK (film IS NULL OR length(film) <= 80),
    duree_s INTEGER CHECK (duree_s IS NULL OR duree_s BETWEEN 1 AND 3600),
    bpm INTEGER CHECK (bpm IS NULL OR bpm BETWEEN 40 AND 240),
    bpm_verifie INTEGER NOT NULL DEFAULT 0 CHECK (bpm_verifie IN (0, 1)),
    statut TEXT NOT NULL DEFAULT 'proposee' CHECK (statut IN ('proposee', 'decidee', 'ecartee')),
    a_creer INTEGER NOT NULL DEFAULT 0 CHECK (a_creer IN (0, 1)),
    createur_id INTEGER REFERENCES cousins (id) ON DELETE SET NULL,
    ordre_passage INTEGER CHECK (ordre_passage IS NULL OR ordre_passage BETWEEN 1 AND 99),
    entree_qui TEXT CHECK (entree_qui IS NULL OR length(entree_qui) <= 200),
    entree_dou TEXT CHECK (entree_dou IS NULL OR length(entree_dou) <= 200),
    musique_qui TEXT CHECK (musique_qui IS NULL OR length(musique_qui) <= 200),
    duree_coupee_s INTEGER CHECK (duree_coupee_s IS NULL OR duree_coupee_s BETWEEN 1 AND 3600),
    video_ref_id INTEGER REFERENCES videos (id) ON DELETE SET NULL,
    note TEXT CHECK (note IS NULL OR length(note) <= 500)
);

CREATE TABLE liens (
    id INTEGER PRIMARY KEY,
    chanson_id INTEGER NOT NULL REFERENCES chansons (id) ON DELETE CASCADE,
    url TEXT NOT NULL CHECK (url LIKE 'https://%' AND length(url) <= 300),
    titre TEXT NOT NULL CHECK (length(titre) BETWEEN 1 AND 120),
    duree_s INTEGER CHECK (duree_s IS NULL OR duree_s BETWEEN 1 AND 36000),
    type TEXT NOT NULL CHECK (type IN ('officiel', 'reference', 'tutoriel')),
    en_miroir INTEGER CHECK (en_miroir IS NULL OR en_miroir IN (0, 1)),
    partage_par TEXT CHECK (partage_par IS NULL OR length(partage_par) <= 40),
    partage_le TEXT,
    UNIQUE (chanson_id, url)
);

CREATE TABLE distributions (
    chanson_id INTEGER NOT NULL REFERENCES chansons (id) ON DELETE CASCADE,
    cousin_id INTEGER NOT NULL REFERENCES cousins (id) ON DELETE CASCADE,
    statut TEXT NOT NULL CHECK (statut IN ('danse', 'en_attente')),
    PRIMARY KEY (chanson_id, cousin_id)
);

CREATE INDEX distributions_cousin ON distributions (cousin_id);

CREATE TABLE sections (
    id INTEGER PRIMARY KEY,
    chanson_id INTEGER NOT NULL REFERENCES chansons (id) ON DELETE CASCADE,
    ordre INTEGER NOT NULL,
    nom TEXT NOT NULL CHECK (length(nom) BETWEEN 1 AND 40),
    debut_s REAL NOT NULL CHECK (debut_s >= 0),
    fin_s REAL NOT NULL,
    comptes INTEGER NOT NULL DEFAULT 1 CHECK (comptes BETWEEN 1 AND 99),
    note TEXT CHECK (note IS NULL OR length(note) <= 200),
    CHECK (fin_s > debut_s)
);

CREATE INDEX sections_chanson ON sections (chanson_id, ordre);

-- Dernier recours (l'administration le dit en toutes lettres avant) : deux sections d'une chanson ne se
-- chevauchent jamais.
CREATE TRIGGER sections_sans_chevauchement_ajout BEFORE INSERT ON sections
WHEN EXISTS (SELECT 1 FROM sections WHERE chanson_id = NEW.chanson_id AND NEW.debut_s < fin_s AND NEW.fin_s > debut_s)
BEGIN
    SELECT RAISE(ABORT, 'Deux sections ne se chevauchent pas.');
END;

CREATE TRIGGER sections_sans_chevauchement_modif BEFORE UPDATE OF debut_s, fin_s ON sections
WHEN EXISTS (SELECT 1 FROM sections WHERE chanson_id = NEW.chanson_id AND id <> NEW.id AND NEW.debut_s < fin_s AND NEW.fin_s > debut_s)
BEGIN
    SELECT RAISE(ABORT, 'Deux sections ne se chevauchent pas.');
END;

CREATE TABLE placements (
    section_id INTEGER NOT NULL REFERENCES sections (id) ON DELETE CASCADE,
    cousin_id INTEGER NOT NULL REFERENCES cousins (id) ON DELETE CASCADE,
    x INTEGER NOT NULL CHECK (x BETWEEN 0 AND 100),
    y INTEGER NOT NULL CHECK (y BETWEEN 0 AND 100),
    PRIMARY KEY (section_id, cousin_id)
);

CREATE TABLE niveaux (
    cousin_id INTEGER NOT NULL REFERENCES cousins (id) ON DELETE CASCADE,
    section_id INTEGER NOT NULL REFERENCES sections (id) ON DELETE CASCADE,
    niveau INTEGER NOT NULL CHECK (niveau IN (0, 1, 2)),
    maj_le TEXT NOT NULL,
    PRIMARY KEY (cousin_id, section_id)
);

CREATE INDEX niveaux_section ON niveaux (section_id);
CREATE INDEX niveaux_maj ON niveaux (niveau, maj_le);

CREATE TABLE repetitions (
    id INTEGER PRIMARY KEY,
    date TEXT NOT NULL UNIQUE CHECK (date GLOB '[0-9][0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9]'),
    heure TEXT CHECK (heure IS NULL OR length(heure) <= 60),
    lieu TEXT CHECK (lieu IS NULL OR length(lieu) <= 120),
    statut TEXT NOT NULL CHECK (statut IN ('proposee', 'decidee', 'passee', 'annulee')),
    travail TEXT CHECK (travail IS NULL OR length(travail) <= 200),
    compte_rendu TEXT CHECK (compte_rendu IS NULL OR length(compte_rendu) <= 2000),
    source TEXT NOT NULL CHECK (source IN ('whatsapp', 'site')),
    note TEXT CHECK (note IS NULL OR length(note) <= 300)
);

CREATE TABLE presences (
    repetition_id INTEGER NOT NULL REFERENCES repetitions (id) ON DELETE CASCADE,
    cousin_id INTEGER NOT NULL REFERENCES cousins (id) ON DELETE CASCADE,
    reponse TEXT NOT NULL CHECK (reponse IN ('oui', 'non', 'peut_etre')),
    source TEXT NOT NULL CHECK (source IN ('whatsapp', 'site')),
    maj_le TEXT NOT NULL,
    present_constate INTEGER CHECK (present_constate IS NULL OR present_constate IN (0, 1)),
    PRIMARY KEY (repetition_id, cousin_id)
);

CREATE INDEX presences_repetition ON presences (repetition_id);

CREATE TABLE commentaires (
    id INTEGER PRIMARY KEY,
    video_id INTEGER NOT NULL REFERENCES videos (id) ON DELETE CASCADE,
    cousin_id INTEGER REFERENCES cousins (id) ON DELETE SET NULL,
    temps_s REAL CHECK (temps_s IS NULL OR temps_s >= 0),
    texte TEXT NOT NULL CHECK (length(texte) BETWEEN 1 AND 200),
    cree_le TEXT NOT NULL,
    supprime_le TEXT
);

CREATE INDEX commentaires_video ON commentaires (video_id);

CREATE TABLE checklist (
    id INTEGER PRIMARY KEY,
    libelle TEXT NOT NULL CHECK (length(libelle) BETWEEN 1 AND 80),
    ordre INTEGER NOT NULL,
    fait INTEGER NOT NULL DEFAULT 0 CHECK (fait IN (0, 1)),
    fait_par INTEGER REFERENCES cousins (id) ON DELETE SET NULL,
    fait_le TEXT
);

UPDATE reglages SET valeur = '2' WHERE cle = 'version_schema';
