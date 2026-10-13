-- Socle (phase 2) : cousins, réglages, journal, limitation de débit, migrations.
-- Dates en texte ISO 8601 UTC. Semence : les neuf prénoms de CLAUDE.md, dans l'ordre alphabétique ;
-- Patrice est l'administrateur ; Jessica et Stéphane ne sont pas dans le groupe WhatsApp (docs/whatsapp/etat.json).
-- Rien d'autre n'est semé ici : contacts, chansons et répétitions viennent de scripts/importer-etat.php (phase 4).

CREATE TABLE IF NOT EXISTS migrations (
    version TEXT PRIMARY KEY,
    applique_le TEXT NOT NULL
);

CREATE TABLE cousins (
    id INTEGER PRIMARY KEY,
    prenom TEXT NOT NULL UNIQUE CHECK (length(prenom) BETWEEN 1 AND 40),
    ordre INTEGER NOT NULL,
    dans_le_groupe_whatsapp INTEGER NOT NULL DEFAULT 1 CHECK (dans_le_groupe_whatsapp IN (0, 1)),
    contact_whatsapp TEXT,
    est_admin INTEGER NOT NULL DEFAULT 0 CHECK (est_admin IN (0, 1)),
    actif INTEGER NOT NULL DEFAULT 1 CHECK (actif IN (0, 1))
);

CREATE TABLE reglages (
    cle TEXT PRIMARY KEY,
    valeur TEXT
);

CREATE TABLE journal (
    id INTEGER PRIMARY KEY,
    quand TEXT NOT NULL,
    type TEXT NOT NULL CHECK (type IN (
        'entree', 'prenom', 'niveau', 'presence', 'video_ajout', 'video_suppr', 'commentaire', 'placement',
        'createur', 'check', 'admin_lien', 'admin_modif', 'refus_debit', 'refus_jeton'
    )),
    cousin_id INTEGER REFERENCES cousins (id) ON DELETE SET NULL,
    detail TEXT CHECK (detail IS NULL OR length(detail) <= 200)
);

CREATE INDEX journal_quand ON journal (quand);
CREATE INDEX journal_type ON journal (type);

-- Journal en ajout seul : aucune ligne ne se modifie (la purge à un an supprime).
CREATE TRIGGER journal_ajout_seul BEFORE UPDATE ON journal
BEGIN
    SELECT RAISE(ABORT, 'Le journal ne se modifie pas.');
END;

CREATE TABLE debit (
    cle TEXT NOT NULL CHECK (length(cle) = 64),
    fenetre TEXT NOT NULL,
    compteur INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (cle, fenetre)
) WITHOUT ROWID;

INSERT INTO cousins (prenom, ordre, dans_le_groupe_whatsapp, est_admin) VALUES
    ('Alex', 1, 1, 0),
    ('Catherine', 2, 1, 0),
    ('Fabien', 3, 1, 0),
    ('Flobert', 4, 1, 0),
    ('Jessica', 5, 0, 0),
    ('Josiane', 6, 1, 0),
    ('Nadine', 7, 1, 0),
    ('Patrice', 8, 1, 1),
    ('Stéphane', 9, 0, 0);

INSERT INTO reglages (cle, valeur) VALUES ('version_schema', '1');
