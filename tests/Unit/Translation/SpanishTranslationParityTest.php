<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * The Spanish translation of this fork is made from the English files. Every
 * translations/**\/<domain>.en.yaml needs a sibling <domain>.es.yaml with the same keys,
 * placeholders, plural intervals and HTML tags, so the interface never shows raw keys or
 * breaks parameter substitution.
 *
 * While "es" is not an enabled locale, files that are not translated yet are reported as
 * incomplete; once it is enabled, a missing file is a failure.
 */
final class SpanishTranslationParityTest extends TestCase
{
    private const PLACEHOLDER = '/%[A-Za-z0-9_.-]+%|\{\{\s*[A-Za-z0-9_.]+\s*\}\}|\{[A-Za-z_][A-Za-z0-9_]*\}/';
    private const PLURAL_INTERVAL = '/^\s*(\{[^}]*\}|[\[\]][^\[\]]*[\[\]])/';
    private const HTML_TAG = '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/';

    /** @return iterable<string, array{string}> */
    public static function englishFiles(): iterable
    {
        $root = self::translationsDir();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $found = [];
        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.en.yaml')) {
                $found[] = substr($file->getPathname(), \strlen($root) + 1);
            }
        }
        sort($found);

        foreach ($found as $relative) {
            yield $relative => [$relative];
        }
    }

    public function testEnglishIsTheOnlySourceFormat(): void
    {
        $xliff = glob(self::translationsDir().'/{,*/}*.en.{xlf,xliff}', \GLOB_BRACE) ?: [];

        self::assertSame([], $xliff, 'The parity check reads English YAML files only.');
    }

    #[DataProvider('englishFiles')]
    public function testSpanishFileMatchesTheEnglishSource(string $englishFile): void
    {
        $spanishFile = preg_replace('/\.en\.yaml$/', '.es.yaml', $englishFile);
        self::assertIsString($spanishFile);
        $spanishPath = self::translationsDir().'/'.$spanishFile;

        if (!is_file($spanishPath)) {
            if (self::spanishIsEnabled()) {
                self::fail(sprintf('Missing Spanish translation %s for %s.', $spanishFile, $englishFile));
            }
            self::markTestIncomplete(sprintf('%s is not translated yet.', $englishFile));
        }

        $english = self::load($englishFile);
        $spanish = self::load($spanishFile);

        self::assertSame([], array_values(array_diff(array_keys($english), array_keys($spanish))), 'Keys missing in '.$spanishFile);
        self::assertSame([], array_values(array_diff(array_keys($spanish), array_keys($english))), 'Keys only in '.$spanishFile);

        foreach ($english as $key => $source) {
            $target = $spanish[$key];
            self::assertNotSame('', trim($target), sprintf('%s: "%s" is empty.', $spanishFile, $key));
            self::assertSame(self::placeholders($source), self::placeholders($target), sprintf('%s: placeholders of "%s" differ.', $spanishFile, $key));
            self::assertSame(self::pluralIntervals($source), self::pluralIntervals($target), sprintf('%s: plural forms of "%s" differ.', $spanishFile, $key));
            self::assertSame(self::htmlTags($source), self::htmlTags($target), sprintf('%s: HTML tags of "%s" differ.', $spanishFile, $key));
        }
    }

    private static function translationsDir(): string
    {
        return \dirname(__DIR__, 3).'/translations';
    }

    private static function spanishIsEnabled(): bool
    {
        $config = Yaml::parseFile(\dirname(__DIR__, 3).'/config/packages/translation.yaml');

        return \in_array('es', $config['framework']['enabled_locales'] ?? [], true);
    }

    /** @return array<string, string> flattened keys as Symfony loads them */
    private static function load(string $relative): array
    {
        $domain = 'parity';
        $messages = (new YamlFileLoader())->load(self::translationsDir().'/'.$relative, 'xx', $domain)->all($domain);

        return array_map(static fn (mixed $text): string => (string) $text, $messages);
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);
        $found = array_map(static fn (string $token): string => preg_replace('/\s+/', '', $token) ?? $token, $matches[0]);
        sort($found);

        return $found;
    }

    /** @return list<string> interval prefix of each plural segment, empty for plain messages */
    private static function pluralIntervals(string $text): array
    {
        $intervals = [];
        foreach (explode('|', $text) as $segment) {
            if (preg_match(self::PLURAL_INTERVAL, $segment, $match)) {
                $intervals[] = preg_replace('/\s+/', '', $match[1]) ?? $match[1];
            }
        }

        return $intervals;
    }

    /** @return list<string> */
    private static function htmlTags(string $text): array
    {
        preg_match_all(self::HTML_TAG, $text, $matches);
        $tags = array_map('strtolower', $matches[1]);
        sort($tags);

        return $tags;
    }
}
