<?php

declare(strict_types=1);

namespace Chores;

// Routeur maison : méthode + motif (« /e/{jeton} ») → action et niveau d'accès ; 404 et 405 propres.

final class Routeur
{
    /** @var list<array{methode: string, motif: string, regex: string, action: callable, niveau: string}> */
    private array $routes = [];

    public function ajouter(string $methode, string $motif, callable $action, string $niveau): self
    {
        if (!in_array($niveau, ['public', 'acces', 'prenom', 'admin', 'gabarits', 'installation'], true)) {
            throw new \InvalidArgumentException('Niveau d’accès inconnu.');
        }
        $regex = '#^' . preg_replace_callback(
            '/\\\\\{([a-z_]+)\\\\\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($motif, '#')
        ) . '$#';
        $this->routes[] = ['methode' => strtoupper($methode), 'motif' => $motif, 'regex' => $regex, 'action' => $action, 'niveau' => $niveau];

        return $this;
    }

    /**
     * @return array{statut: 200, action: callable, niveau: string, params: array<string, string>}
     *       |array{statut: 404}
     *       |array{statut: 405, permises: list<string>, niveaux: list<string>}
     */
    public function trouver(string $methode, ?string $chemin): array
    {
        if ($chemin === null) {
            return ['statut' => 404];
        }
        $methode = $methode === 'HEAD' ? 'GET' : strtoupper($methode);
        $permises = [];
        $niveaux = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $chemin, $m)) {
                continue;
            }
            if ($route['methode'] !== $methode) {
                $permises[] = $route['methode'];
                $niveaux[] = $route['niveau'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            return ['statut' => 200, 'action' => $route['action'], 'niveau' => $route['niveau'], 'params' => $params];
        }

        return $permises === [] ? ['statut' => 404] : ['statut' => 405, 'permises' => array_values(array_unique($permises)), 'niveaux' => $niveaux];
    }
}

/** Trouve la route, contrôle l'accès et le CSRF, appelle l'action, pose le cookie CSRF s'il vient d'être créé. */
function traiter(Contexte $c, Routeur $routeur): Reponse
{
    $trouve = $routeur->trouver($c->methode, $c->chemin);
    // Sous /admin (et pour les pages réservées à Patrice), une méthode refusée ne doit pas trahir l'existence d'une
    // route : 404 comme le reste.
    $reservee = $trouve['statut'] === 405 && (str_starts_with((string) $c->chemin, '/admin')
        || array_intersect($trouve['niveaux'], niveaux_reserves($c)) !== []);
    if ($trouve['statut'] === 404 || $reservee) {
        return page_introuvable($c);
    }
    if ($trouve['statut'] === 405) {
        $permises = $trouve['permises'];
        if (in_array('GET', $permises, true)) {
            $permises[] = 'HEAD';
        }

        return page_erreur($c->config, 405, 'Action impossible', 'Cette adresse ne s’utilise pas comme ça.')
            ->avecEntete('Allow', implode(', ', $permises));
    }

    $refus = controler_acces($c, $trouve['niveau']);
    if ($refus !== null) {
        return $refus;
    }

    // Envoi plus lourd que post_max_size : PHP vide $_POST (et le jeton CSRF avec). On le dit en toutes lettres
    // plutôt que « Formulaire expiré ».
    if ($c->methode === 'POST' && $c->post === [] && $c->fichiers === [] && (int) $c->entete('content-length') > 0
        && str_starts_with(strtolower((string) $c->entete('content-type')), 'multipart/form-data')) {
        return page_erreur($c->config, 413, 'Fichier trop lourd', sprintf(
            'Ton fichier dépasse la taille permise (%d Mo au plus). Coupe-le ou demande à Patrice.',
            $c->config['TAILLE_MAX_UPLOAD_MO']
        ));
    }
    if ($c->methode === 'POST' && (!origine_acceptable($c) || !csrf_valide($c->config['SECRET'], $c->cookie(COOKIE_CSRF), $c->champ(CHAMP_CSRF)))) {
        return page_erreur($c->config, 400, 'Formulaire expiré', 'Ce formulaire a expiré. Touche « Revenir à l’accueil » et réessaie.');
    }

    $reponse = ($trouve['action'])($c, $trouve['params']);
    if ($c->nouveauCsrf !== null) {
        $reponse->avecCookie(COOKIE_CSRF, $c->nouveauCsrf, 0);
    }

    return $reponse;
}
