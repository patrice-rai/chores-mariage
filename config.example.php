<?php

// Modèle de configuration, versionné. Copie-le en config.php (jamais versionné), à côté du dossier app/,
// et remplis les valeurs. En local, scripts/config-dev.php le génère tout seul avec des secrets aléatoires.
//
// SECRET : 64 caractères hexadécimaux aléatoires, signe les cookies.
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// CLE_ADMIN : clé d'administration de Patrice (43 caractères base64url), à visiter une fois par appareil :
//   https://[domaine]/chores/admin/cle/<CLE_ADMIN>
//   php -r "echo rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), PHP_EOL;"
// BASE_PATH : '/chores' pour un sous-dossier, '' pour un sous-domaine.
// URL_PUBLIQUE : adresse du site sans BASE_PATH, par exemple 'https://[domaine]' (sert à écrire le lien complet).
// TAILLE_MAX_UPLOAD_MO, ESPACE_MAX_MO : limites des vidéos (à régler selon l'offre Hostinger).
// ENV : 'production' sur l'hébergeur, 'dev' en local seulement (cookies sans Secure, outils de test permis).
// ACCES_LIBRE : true = le site s'ouvre sans passer par le lien secret (tout le monde peut entrer et choisir un
//   prénom ; l'administration reste protégée par CLE_ADMIN) ; false = seul le lien secret ouvre le site.

return [
    'SECRET' => '',
    'CLE_ADMIN' => '',
    'BASE_PATH' => '/chores',
    'URL_PUBLIQUE' => '',
    'TAILLE_MAX_UPLOAD_MO' => 200,
    'ESPACE_MAX_MO' => 5000,
    'ENV' => 'production',
    'ACCES_LIBRE' => false,
];
