<?php

declare(strict_types=1);

namespace Chores;

// Routes des phases 2 à 6 (spec § 2). Niveaux : public, acces (lien), prenom (lien + prénom), admin, gabarits,
// installation (base neuve seulement).

function routes(): Routeur
{
    $a = 'Chores\Controleurs\\';

    return (new Routeur())
        ->ajouter('GET', '/e/{jeton}', $a . 'entrer_par_le_lien', 'public')
        ->ajouter('GET', '/lien', $a . 'lien_manquant', 'public')
        ->ajouter('GET', '/', $a . 'entree', 'acces')
        ->ajouter('POST', '/prenom', $a . 'choisir_prenom', 'acces')
        ->ajouter('POST', '/changer', $a . 'changer_prenom', 'acces')
        ->ajouter('GET', '/moi', $a . 'ma_page', 'prenom')
        // Phase 4 : chansons, niveaux, placements, répétitions en lecture, vidéos de référence.
        ->ajouter('GET', '/chanson/{slug}', $a . 'page_chanson', 'prenom')
        ->ajouter('GET', '/chanson/{slug}/repeter', $a . 'repeter_chanson', 'prenom')
        ->ajouter('POST', '/chanson/{slug}/section/{id}/niveau', $a . 'poser_niveau', 'prenom')
        ->ajouter('POST', '/chanson/{slug}/createur', $a . 'choisir_createur', 'prenom')
        ->ajouter('GET', '/api/etat', $a . 'etat_json', 'prenom')
        ->ajouter('GET', '/placements', $a . 'placements_accueil', 'prenom')
        ->ajouter('GET', '/placements/{slug}', $a . 'page_placements', 'prenom')
        ->ajouter('POST', '/placements/{section}/moi', $a . 'deplacer_mon_point', 'prenom')
        // Phase 5 : répétitions et présences, vidéos des cousins, Jour J.
        ->ajouter('GET', '/repetitions', $a . 'page_repetitions', 'prenom')
        ->ajouter('POST', '/repetitions/{id}/presence', $a . 'repondre_presence', 'prenom')
        ->ajouter('GET', '/videos', $a . 'page_videos', 'prenom')
        ->ajouter('POST', '/videos', $a . 'ajouter_video', 'prenom')
        ->ajouter('GET', '/videos/ajouter', $a . 'formulaire_video', 'prenom')
        ->ajouter('GET', '/videos/{id}', $a . 'page_video', 'prenom')
        ->ajouter('POST', '/videos/{id}/commentaires', $a . 'commenter_video', 'prenom')
        ->ajouter('POST', '/videos/{id}/commentaires/{commentaire}/supprimer', $a . 'supprimer_commentaire', 'prenom')
        ->ajouter('POST', '/videos/{id}/supprimer', $a . 'supprimer_video', 'prenom')
        ->ajouter('POST', '/videos/{id}/duree', $a . 'poser_duree_video', 'prenom')
        ->ajouter('GET', '/jour-j', $a . 'page_jour_j', 'prenom')
        ->ajouter('POST', '/jour-j/check/{id}', $a . 'cocher_checklist', 'prenom')
        ->ajouter('GET', '/m/{id}', $a . 'servir_media', 'acces')
        // PWA : service worker et page de repli, sans cookie (spec § 2).
        ->ajouter('GET', '/sw.js', $a . 'service_worker', 'public')
        ->ajouter('GET', '/hors-ligne', $a . 'page_hors_ligne', 'public')
        // Phase 6 : installation sans SSH, sur une base neuve seulement (404 dès qu'un lien existe).
        ->ajouter('GET', '/installer', $a . 'formulaire_installation', 'installation')
        ->ajouter('POST', '/installer', $a . 'installer', 'installation')
        // Administration de Patrice : 404 sans le cookie d'administration.
        ->ajouter('GET', '/admin/cle/{cle}', $a . 'poser_cle_admin', 'public')
        ->ajouter('GET', '/admin', $a . 'tableau_admin', 'admin')
        ->ajouter('GET', '/admin/lien', $a . 'formulaire_nouveau_lien', 'admin')
        ->ajouter('POST', '/admin/lien', $a . 'creer_nouveau_lien', 'admin')
        ->ajouter('GET', '/admin/base', $a . 'admin_base', 'admin')
        ->ajouter('POST', '/admin/migrer', $a . 'admin_migrer', 'admin')
        ->ajouter('POST', '/admin/sauvegarde', $a . 'admin_sauvegarder', 'admin')
        ->ajouter('POST', '/admin/restaurer', $a . 'admin_restaurer', 'admin')
        ->ajouter('GET', '/admin/reglages', $a . 'admin_reglages', 'admin')
        ->ajouter('POST', '/admin/reglages', $a . 'admin_enregistrer_reglages', 'admin')
        ->ajouter('GET', '/admin/chansons', $a . 'admin_chansons', 'admin')
        ->ajouter('POST', '/admin/chansons', $a . 'admin_ajouter_chanson', 'admin')
        ->ajouter('GET', '/admin/chansons/{slug}', $a . 'admin_chanson', 'admin')
        ->ajouter('POST', '/admin/chansons/{slug}', $a . 'admin_enregistrer_chanson', 'admin')
        ->ajouter('POST', '/admin/chansons/{slug}/liens', $a . 'admin_liens', 'admin')
        ->ajouter('POST', '/admin/chansons/{slug}/reference', $a . 'admin_reference', 'admin')
        ->ajouter('GET', '/admin/chansons/{slug}/sections', $a . 'admin_sections', 'admin')
        ->ajouter('POST', '/admin/chansons/{slug}/sections', $a . 'admin_modifier_sections', 'admin')
        ->ajouter('GET', '/admin/chansons/{slug}/placements', $a . 'admin_placements', 'admin')
        ->ajouter('POST', '/admin/chansons/{slug}/placements', $a . 'admin_enregistrer_placements', 'admin')
        ->ajouter('GET', '/admin/journal', $a . 'admin_journal', 'admin')
        ->ajouter('GET', '/admin/repetitions', $a . 'admin_repetitions', 'admin')
        ->ajouter('POST', '/admin/repetitions', $a . 'admin_ajouter_repetition', 'admin')
        ->ajouter('GET', '/admin/repetitions/{id}', $a . 'admin_repetition', 'admin')
        ->ajouter('POST', '/admin/repetitions/{id}', $a . 'admin_enregistrer_repetition', 'admin')
        ->ajouter('GET', '/admin/jour-j', $a . 'admin_jour_j', 'admin')
        ->ajouter('POST', '/admin/jour-j', $a . 'admin_modifier_jour_j', 'admin')
        ->ajouter('GET', '/admin/videos', $a . 'admin_videos', 'admin')
        ->ajouter('POST', '/admin/videos/{id}/supprimer', $a . 'admin_supprimer_video', 'admin')
        ->ajouter('POST', '/admin/videos/{id}/remplacer', $a . 'admin_remplacer_video', 'admin')
        // Phase 3. Bascule de thème (aucune donnée personnelle, CSRF exigé comme tout POST).
        ->ajouter('POST', '/theme', $a . 'changer_theme', 'public')
        // Gabarits et démonstration : libres en développement, cookie d'administration sinon (niveau « gabarits »,
        // contrôlé avant le CSRF ; une méthode refusée y répond 404 hors développement).
        ->ajouter('GET', '/gabarits', $a . 'gabarits', 'gabarits')
        ->ajouter('POST', '/gabarits/essai', $a . 'essai_gabarits', 'gabarits')
        ->ajouter('GET', '/gabarits/demo.webm', $a . 'video_demo', 'gabarits')
        ->ajouter('GET', '/gabarits/repeter', $a . 'repeter', 'gabarits');
}
