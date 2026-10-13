<?php

declare(strict_types=1);

namespace Chores;

// En-têtes de sécurité envoyés sur chaque réponse PHP (les fichiers statiques reçoivent les mêmes par .htaccess).

/** @return array<string, string> */
function entetes_securite(array $config): array
{
    $production = ($config['ENV'] ?? 'production') === 'production';
    $csp = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; "
        . "font-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'none'; "
        . "form-action 'self'; base-uri 'self'; object-src 'none'";
    if ($production) {
        $csp .= '; upgrade-insecure-requests';
    }

    $entetes = [
        'Content-Security-Policy' => $csp,
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];
    if ($production) {
        $entetes['Strict-Transport-Security'] = 'max-age=31536000';
    }

    return $entetes;
}
