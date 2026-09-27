<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppSettings;
use App\Sqlite\SqliteBaselineInitializer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Translation\Translator;

/**
 * The fork ships a Spanish interface translated from English. With LOCALE=es the
 * interface is Spanish, and any message missing in Spanish falls back to English (the
 * source of the translation) rather than German.
 */
final class SpanishLocaleTest extends WebTestCase
{
    private string $fixturePath;
    private ?string $previousLocale = null;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtureDirectory = dirname(__DIR__, 2).'/var/sqlite-test';
        if (!is_dir($fixtureDirectory)) {
            mkdir($fixtureDirectory, 0770, true);
        }
        $this->fixturePath = $fixtureDirectory.'/fewohbee.sqlite';
        $this->removeFixture();

        $this->previousLocale = $_SERVER['LOCALE'] ?? null;
        $_SERVER['LOCALE'] = $_ENV['LOCALE'] = 'es';
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        if (null === $this->previousLocale) {
            unset($_SERVER['LOCALE'], $_ENV['LOCALE']);
        } else {
            $_SERVER['LOCALE'] = $_ENV['LOCALE'] = $this->previousLocale;
        }
        $this->removeFixture();

        parent::tearDown();
    }

    public function testSpanishIsAnEnabledLocaleThatFallsBackToEnglish(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        self::assertContains('es', self::getContainer()->getParameter('kernel.enabled_locales'));

        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(Translator::class, $translator);
        self::assertSame('Iniciar sesión', $translator->trans('login.title', [], 'messages', 'es'));
        self::assertSame('en', $translator->getCatalogue('es')->getFallbackCatalogue()?->getLocale());
    }

    public function testDefaultCustomerSalutationsAreTranslated(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(Translator::class, $translator);

        // Salutations are translated by key and the translated text is stored on the customer.
        $expected = ['Ms' => ['Ms', 'Sra.'], 'Mr' => ['Mr', 'Sr.'], 'Family' => ['Family', 'Familia']];
        foreach ((new AppSettings())->getCustomerSalutations() as $salutation) {
            self::assertArrayHasKey($salutation, $expected);
            self::assertSame($expected[$salutation][0], $translator->trans($salutation, [], 'messages', 'en'));
            self::assertSame($expected[$salutation][1], $translator->trans($salutation, [], 'messages', 'es'));
        }
    }

    public function testLoginPageIsSpanishWithLocaleEs(): void
    {
        $client = self::createClient(['environment' => 'sqlite_test']);
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSame('es', $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextContains('label[for="floatingInput"]', 'Nombre de usuario');
        self::assertSelectorTextContains('label[for="floatingPassword"]', 'Contraseña');
    }

    private function removeFixture(): void
    {
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
