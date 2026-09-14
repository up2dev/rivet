<?php

declare(strict_types=1);

/**
 * Config du front consommé par Rivet pour construire des liens
 * cliquables dans les emails (mot de passe, etc.) — jamais un chemin
 * en dur dans un template Blade, toujours piloté par cette config
 * (cohérent avec le principe déjà énoncé par Rivet : "rien de cassé
 * sous config:cache, chaque variable d'environnement passe par un
 * fichier de config").
 */
return [
    // URL racine du front (déjà utilisée ailleurs via app.frontend_url —
    // reprise ici pour ne dépendre que de ce seul fichier de config).
    'url' => env('FRONTEND_URL', config('app.frontend_url')),

    // Locales gérées par le front, et celle par défaut si l'en-tête
    // Accept-Language est absent ou ne matche aucune locale supportée.
    'locales' => array_filter(explode(',', (string) env('FRONTEND_LOCALES', 'fr'))),
    'default_locale' => env('FRONTEND_DEFAULT_LOCALE', 'fr'),

    // Le front ne préfixe ses URLs par /{locale}/ QUE s'il gère plusieurs
    // locales (voir router/index.js du front, isMultiLocale) — à activer
    // manuellement ici plutôt que de le déduire de count(locales) > 1,
    // pour rester explicite si un jour une seule locale est configurée
    // mais que le préfixe doit rester actif (migration progressive, etc.).
    'multi_locale' => filter_var(env('FRONTEND_MULTI_LOCALE', false), FILTER_VALIDATE_BOOLEAN),

    // Chemin (slug) de chaque route front concernée, par locale — copié
    // à la main depuis les fichiers i18n du front (src/i18n/locales/**),
    // pas de source de vérité partagée entre les deux repos pour l'instant.
    'paths' => [
        // Un seul et même parcours pour "mot de passe oublié" ET
        // "création du mot de passe initial" (même page front, même
        // route passwordForgottenStep2) — voir docs internes.
        'password' => [
            'fr' => 'mot-de-passe-oublie',
            'en' => 'forgotten-password',
        ],
    ],
];
