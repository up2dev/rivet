<?php
/**
 * FrontendUrl class file
 *
 * PHP Version 8.1
 *
 * @category Support
 * @package  Rivet\Support
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Support;

/**
 * Construit une URL cliquable vers le front, localisée d'après
 * l'en-tête Accept-Language de la requête EN COURS.
 *
 * IMPORTANT : à appeler UNIQUEMENT de façon synchrone, au moment où
 * la requête HTTP d'origine existe encore (dans le contrôleur/le
 * model event, avant tout Mail::send()) — jamais depuis l'intérieur
 * d'un template Blade ou d'un job de queue, où request() peut être
 * absent ou ne plus refléter la requête initiale (l'envoi de mail
 * chez Rivet passe potentiellement par une file d'attente
 * asynchrone, SendmailService/ProcessSendmail). L'URL déjà résolue
 * est passée en donnée de vue, jamais recalculée dans le template.
 *
 * @category Support
 * @package  Rivet\Support
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FrontendUrl
{
    /**
     * Construit l'URL complète vers une route du front, avec le
     * préfixe de locale si le front en gère plusieurs, et un segment
     * de token optionnel à la fin (ex: /mot-de-passe-oublie/{token}).
     *
     * @param string      $pathKey La clé dans config('frontend.paths')
     * @param string|null $token   Segment de token optionnel en fin d'URL
     *
     * @return string
     */
    public static function build(string $pathKey, ?string $token = null): string
    {
        $locale = self::resolveLocale();
        $defaultLocale = config('frontend.default_locale', 'fr');

        $path = config("frontend.paths.{$pathKey}.{$locale}")
            ?? config("frontend.paths.{$pathKey}.{$defaultLocale}");

        $base = rtrim((string) config('frontend.url'), '/');
        $localePrefix = config('frontend.multi_locale', false) ? "/{$locale}" : '';
        $tokenSuffix = $token !== null ? '/' . $token : '';

        return "{$base}{$localePrefix}/{$path}{$tokenSuffix}";
    }

    /**
     * Résout la locale à utiliser : le premier code langue de
     * l'en-tête Accept-Language qui correspond à une locale gérée par
     * le front, sinon la locale par défaut — y compris si l'en-tête
     * est absent, ou si request() n'est pas résolvable (contexte hors
     * cycle HTTP, ex: commande artisan).
     *
     * @return string
     */
    public static function resolveLocale(): string
    {
        $supported = config('frontend.locales', ['fr']);
        $default = config('frontend.default_locale', 'fr');

        if (!app()->bound('request')) {
            return $default;
        }

        $header = request()->header('Accept-Language');

        if (empty($header)) {
            return $default;
        }

        foreach (explode(',', $header) as $part) {
            $langTag = trim(explode(';', $part)[0] ?? '');
            $lang = strtolower(substr($langTag, 0, 2));

            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return $default;
    }
}
