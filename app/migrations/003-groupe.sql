-- Groupe (phase 5, spec § 2 et 3) : présents et absents constatés par Patrice, distincts des réponses « je viens »
-- (un cousin qui n'a rien répondu peut avoir été là : la colonne presences.present_constate exigerait une réponse
-- inventée, elle reste inutilisée) ; index des lectures de la phase (album, commentaires, « Ce qui bouge »).
-- Ni DROP, ni modification des tables existantes.

CREATE TABLE constats (
    repetition_id INTEGER NOT NULL REFERENCES repetitions (id) ON DELETE CASCADE,
    cousin_id INTEGER NOT NULL REFERENCES cousins (id) ON DELETE CASCADE,
    present INTEGER NOT NULL CHECK (present IN (0, 1)),
    PRIMARY KEY (repetition_id, cousin_id)
);

CREATE INDEX commentaires_temps ON commentaires (video_id, temps_s, cree_le);
CREATE INDEX videos_album ON videos (type, supprime_le, cree_le);
CREATE INDEX presences_fil ON presences (source, maj_le);

UPDATE reglages SET valeur = '3' WHERE cle = 'version_schema';
