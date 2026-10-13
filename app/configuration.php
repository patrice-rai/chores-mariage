<?php

declare(strict_types=1);

namespace Chores;

// Lecture et contrôle de la configuration (fichier non versionné à côté de app/, modèle : config.example.php).

/** @return array{SECRET: string, CLE_ADMIN: string, BASE_PATH: string, URL_PUBLIQUE: string, TAILLE_MAX_UPLOAD_MO: int, ESPACE_MAX_MO: int, ENV: string, ACCES_LIBRE: bool} */
function charger_configuration(?string $fichier = null): array
{
    $fichier ??= RACINE . '/config.php';
    if (!is_file($fichier)) {
        throw new \RuntimeException('Configuration absente : copier config.example.php et remplir les valeurs.');
    }
    $brute = require $fichier;
    if (!is_array($brute)) {
        throw new \RuntimeException('Configuration illisible : le fichier doit renvoyer un tableau.');
    }

    return normaliser_configuration($brute);
}

/**
 * @param array<string, mixed> $brute
 * @return array{SECRET: string, CLE_ADMIN: string, BASE_PATH: string, URL_PUBLIQUE: string, TAILLE_MAX_UPLOAD_MO: int, ESPACE_MAX_MO: int, ENV: string, ACCES_LIBRE: bool}
 */
function normaliser_configuration(array $brute): array
{
    $secret = (string) ($brute['SECRET'] ?? '');
    if (strlen($secret) < 32) {
        throw new \RuntimeException('Configuration : SECRET doit compter au moins 32 caractères.');
    }
    $env = (string) ($brute['ENV'] ?? 'production');
    if (!in_array($env, ['dev', 'production'], true)) {
        throw new \RuntimeException('Configuration : ENV vaut « dev » ou « production ».');
    }

    return [
        'SECRET' => $secret,
        // Une clé trop courte désactive l'administration plutôt que de l'affaiblir.
        'CLE_ADMIN' => strlen((string) ($brute['CLE_ADMIN'] ?? '')) >= 32 ? (string) $brute['CLE_ADMIN'] : '',
        'BASE_PATH' => normaliser_base((string) ($brute['BASE_PATH'] ?? '')),
        'URL_PUBLIQUE' => rtrim((string) ($brute['URL_PUBLIQUE'] ?? ''), '/'),
        'TAILLE_MAX_UPLOAD_MO' => max(1, (int) ($brute['TAILLE_MAX_UPLOAD_MO'] ?? 200)),
        'ESPACE_MAX_MO' => max(1, (int) ($brute['ESPACE_MAX_MO'] ?? 5000)),
        'ENV' => $env,
        // Accès libre (13 octobre 2026) : le site s'ouvre sans le lien secret ; le cookie d'accès est posé au premier
        // passage. Le choix du prénom et l'administration (clé) ne changent pas.
        'ACCES_LIBRE' => filter_var($brute['ACCES_LIBRE'] ?? false, FILTER_VALIDATE_BOOL),
    ];
}

/** « chores », « /chores/ » → « /chores » ; « / » ou vide → « ». */
function normaliser_base(string $base): string
{
    $base = trim($base, " \t/");
    if ($base === '') {
        return '';
    }
    if (!preg_match('#^[A-Za-z0-9._~-]+(/[A-Za-z0-9._~-]+)*$#', $base)) {
        throw new \RuntimeException('Configuration : BASE_PATH ne contient que des lettres, chiffres, « - », « _ », « . » et « / ».');
    }

    return '/' . $base;
}
