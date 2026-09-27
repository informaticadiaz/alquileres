<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Sqlite\SqliteBaselineInitializer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

/**
 * Walks every parameterless admin page with LOCALE=es and sample data, and fails on
 * interface text that is still English (an English catalogue value whose Spanish
 * translation differs) or German. Catches hard-coded strings and form labels without a
 * translation key, which the file-level parity check cannot see.
 */
final class SpanishInterfaceWalkthroughTest extends WebTestCase
{
    /** Pages that need query parameters or are not HTML. */
    private const SKIPPED_PATH = '#^/(_|api|health|book|logout|login|webauthn|oidc|passkey|ical|reset-password|release-notes)|/(export|pdf|openapi)|^/reservation/calendar-entry/new$|^/settings/online-booking/rules/calendar$|^/statistics/snapshot/monthly$#';
    /** Sample content, not interface text: the example CSV header of a German bank export. */
    private const ALLOWED_TEXT = '/^"Buchungsdatum";/';
    private const GERMAN = '/[äöüßÄÖÜ]|\b(und|oder|nicht|Bitte|Zimmer|Rechnung|Buchung|Gäste|Preis|Einstellungen|Speichern|Löschen|Anreise|Abreise|Kunde|Zurück)\b/u';

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

    public function testAdminPagesShowNoEnglishOrGermanInterfaceText(): void
    {
        $client = self::createClient(['environment' => 'sqlite_test']);
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();
        $firstRun = new CommandTester((new Application(self::$kernel))->find('app:first-run'));
        self::assertSame(Command::SUCCESS, $firstRun->execute([
            '--username' => 'walkthrough',
            '--password' => 'safe-test-password',
            '--first-name' => 'Recorrido',
            '--last-name' => 'Prueba',
            '--email' => 'walkthrough@example.test',
            '--accommodation-name' => 'Alojamiento de prueba',
            '--load-sample-data' => true,
        ], ['interactive' => false]), $firstRun->getDisplay());

        $english = $this->untranslatedEnglishTexts();
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'walkthrough']);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'main');

        $findings = [];
        $visited = 0;
        foreach ($this->parameterlessAdminPaths() as $path) {
            $client->request('GET', $path);
            $response = $client->getResponse();
            self::assertLessThan(500, $response->getStatusCode(), sprintf('%s answered %d.', $path, $response->getStatusCode()));
            if ($response->getStatusCode() >= 300 || !str_contains((string) $response->headers->get('Content-Type'), 'html')) {
                continue;
            }
            ++$visited;

            foreach ($this->visibleTexts((string) $response->getContent()) as $text) {
                if (1 === preg_match(self::ALLOWED_TEXT, $text)) {
                    continue;
                }
                if (isset($english[$text])) {
                    $findings[] = sprintf('%s: English "%s" (%s)', $path, $text, $english[$text]);
                } elseif (1 === preg_match(self::GERMAN, $text)) {
                    $findings[] = sprintf('%s: German "%s"', $path, mb_substr($text, 0, 80));
                }
            }
        }

        self::assertGreaterThan(60, $visited, 'Too few pages rendered; the walkthrough lost its coverage.');
        self::assertSame([], array_values(array_unique($findings)));
    }

    /** @return array<string, string> English catalogue text => domain:key, where Spanish differs */
    private function untranslatedEnglishTexts(): array
    {
        $translator = self::getContainer()->get('translator');
        $en = $translator->getCatalogue('en');
        $es = $translator->getCatalogue('es');
        $texts = [];
        foreach (['messages', 'validators', 'Housekeeping', 'security'] as $domain) {
            foreach ($en->all($domain) as $key => $text) {
                $source = self::normalize((string) $text);
                if ($source !== self::normalize((string) $es->get((string) $key, $domain)) && mb_strlen($source) >= 4 && 1 === preg_match('/[a-z]{3}/i', $source)) {
                    $texts[$source] = $domain.':'.$key;
                }
            }
        }

        return $texts;
    }

    /** @return list<string> */
    private function parameterlessAdminPaths(): array
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $paths = [];
        foreach ($router->getRouteCollection() as $route) {
            $methods = $route->getMethods();
            $path = $route->getPath();
            if (([] === $methods || \in_array('GET', $methods, true)) && !str_contains($path, '{') && 1 !== preg_match(self::SKIPPED_PATH, $path)) {
                $paths[] = $path;
            }
        }
        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /** @return list<string> text nodes and user-facing attributes of the page body */
    private function visibleTexts(string $html): array
    {
        $crawler = new Crawler($html);
        $texts = $crawler->filterXPath('//body//text()[normalize-space()][not(ancestor::script)][not(ancestor::style)]')
            ->each(static fn (Crawler $node): string => self::normalize($node->text()));
        foreach ($crawler->filterXPath('//body//*[@placeholder or @title or @aria-label]') as $element) {
            foreach (['placeholder', 'title', 'aria-label'] as $attribute) {
                if ($element instanceof \DOMElement && '' !== $element->getAttribute($attribute)) {
                    $texts[] = self::normalize($element->getAttribute($attribute));
                }
            }
        }

        return array_values(array_unique($texts));
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
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
