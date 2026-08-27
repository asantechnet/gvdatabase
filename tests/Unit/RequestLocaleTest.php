<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class RequestLocaleTest extends TestCase
{
    private LocaleTestApplication $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new LocaleTestApplication;
        $this->app->instance('config', new Repository([
            'app' => ['locale' => 'en'],
            'localization' => [
                'accepted_locales' => [
                    'en' => 'en',
                    'fa-AF' => 'fa',
                    'ps-AF' => 'ps',
                ],
            ],
        ]));
        Container::setInstance($this->app);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_uses_the_laravel_lang_locale_for_the_frontend_language(): void
    {
        $this->assertSame('fa', $this->localeFor('fa-AF'));
    }

    public function test_it_honors_accept_language_preference_order_for_supported_locales(): void
    {
        $this->assertSame('ps', $this->localeFor('de-DE, ps-AF;q=0.9, en;q=0.8'));
    }

    public function test_it_uses_the_configured_locale_when_the_header_is_unsupported(): void
    {
        $this->assertSame('en', $this->localeFor('de-DE'));
    }

    private function localeFor(string $acceptLanguage): string
    {
        $request = Request::create('/api/test-locale', 'GET', server: [
            'HTTP_ACCEPT_LANGUAGE' => $acceptLanguage,
        ]);

        (new SetLocaleFromRequest)->handle($request, fn (): Response => new Response);

        return $this->app->getLocale();
    }
}

class LocaleTestApplication extends Container
{
    private string $locale = 'en';

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
