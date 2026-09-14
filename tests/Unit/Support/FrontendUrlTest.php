<?php
/**
 * FrontendUrlTest class file
 *
 * PHP Version 8.1
 */
namespace Tests\Unit\Support;

use Illuminate\Http\Request;
use Rivet\Support\FrontendUrl;
use Rivet\Tests\TestCase;

class FrontendUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'frontend.url' => 'https://app.example.com',
            'frontend.locales' => ['fr', 'en'],
            'frontend.default_locale' => 'fr',
            'frontend.multi_locale' => true,
            'frontend.paths' => [
                'password' => [
                    'fr' => 'mot-de-passe-oublie',
                    'en' => 'forgotten-password',
                ],
            ],
        ]);
    }

    /**
     * Simule une requête entrante avec l'en-tête Accept-Language donné,
     * sans passer par un vrai round-trip HTTP (pas de route nécessaire).
     */
    private function bindRequestWithAcceptLanguage(?string $header): void
    {
        $request = Request::create('/', 'GET');
        // Request::create() peut hériter d'un Accept-Language déjà
        // présent dans l'environnement du process PHP (via $_SERVER) —
        // on force explicitement son absence plutôt que de compter sur
        // le simple fait de ne pas appeler set().
        $request->headers->remove('Accept-Language');

        if ($header !== null) {
            $request->headers->set('Accept-Language', $header);
        }

        $this->app->instance('request', $request);
    }

    public function test_it_resolves_the_matching_locale_from_accept_language(): void
    {
        $this->bindRequestWithAcceptLanguage('en-US,en;q=0.9,fr;q=0.8');

        $this->assertSame('en', FrontendUrl::resolveLocale());
    }

    public function test_it_falls_back_to_default_locale_when_header_is_missing(): void
    {
        $this->bindRequestWithAcceptLanguage(null);

        $this->assertSame('fr', FrontendUrl::resolveLocale());
    }

    public function test_it_falls_back_to_default_locale_when_no_supported_locale_matches(): void
    {
        // Ni "de" ni rien d'autre dans la liste ne matche fr/en.
        $this->bindRequestWithAcceptLanguage('de-DE,de;q=0.9');

        $this->assertSame('fr', FrontendUrl::resolveLocale());
    }

    public function test_it_builds_a_localized_url_with_locale_prefix_and_token(): void
    {
        $this->bindRequestWithAcceptLanguage('en-US,en;q=0.9');

        $url = FrontendUrl::build('password', 'abc123');

        $this->assertSame(
            'https://app.example.com/en/forgotten-password/abc123',
            $url
        );
    }

    public function test_it_builds_a_localized_url_in_default_locale(): void
    {
        $this->bindRequestWithAcceptLanguage('fr-FR,fr;q=0.9');

        $url = FrontendUrl::build('password', 'abc123');

        $this->assertSame(
            'https://app.example.com/fr/mot-de-passe-oublie/abc123',
            $url
        );
    }

    public function test_it_omits_the_locale_prefix_when_multi_locale_is_disabled(): void
    {
        config(['frontend.multi_locale' => false]);
        $this->bindRequestWithAcceptLanguage('fr-FR,fr;q=0.9');

        $url = FrontendUrl::build('password', 'abc123');

        $this->assertSame(
            'https://app.example.com/mot-de-passe-oublie/abc123',
            $url
        );
    }

    public function test_it_builds_a_url_without_token_when_none_is_given(): void
    {
        $this->bindRequestWithAcceptLanguage('fr-FR,fr;q=0.9');

        $url = FrontendUrl::build('password');

        $this->assertSame(
            'https://app.example.com/fr/mot-de-passe-oublie',
            $url
        );
    }
}
