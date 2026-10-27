<?php

namespace Tests\Unit;

use App\Support\ApiMessage;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\TestCase;

class ApiMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_returns_the_message_for_the_current_laravel_locale(): void
    {
        $this->configureTranslator('fa');

        $this->assertSame('شما اجازه انجام این کار را ندارید.', ApiMessage::forCode('auth.forbidden'));
    }

    public function test_it_uses_a_category_default_for_unknown_codes(): void
    {
        $this->configureTranslator('ps');

        $this->assertSame('دا کار په اوسني حالت کې اجازه نه لري.', ApiMessage::forCode('business.unknown'));
    }

    public function test_business_errors_have_specific_english_messages(): void
    {
        $this->configureTranslator('en');

        $this->assertSame(
            'The payment exceeds the remaining balance of this service.',
            ApiMessage::forCode('business.contract_payment_exceeds_balance'),
        );
    }

    public function test_new_business_errors_are_localized_in_persian(): void
    {
        $this->configureTranslator('fa');

        $this->assertSame(
            'برای یک یا چند تاریخ انتخاب‌شده قبلاً درخواست رخصتی ثبت شده است.',
            ApiMessage::forCode('business.leave_request_date_exists'),
        );
    }

    private function configureTranslator(string $locale): void
    {
        $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), $locale);
        $translator->setFallback('en');

        $app = new Container;
        $app->instance('translator', $translator);
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstance('translator');
    }
}
