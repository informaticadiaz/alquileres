<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exposure baseline of the operational SQLite profile behind the Cloudflare tunnel:
 * cloudflared on 127.0.0.1 is the only trusted proxy, responses carry security headers,
 * and repeated failed logins are throttled per visitor.
 */
final class SqliteProdSecurityTest extends TestCase
{
    private const HOST = 'alquileres.example.test';
    private const VISITOR_IP = '203.0.113.9';

    private string $databasePath;
    private ?Kernel $kernel = null;
    /** @var array<string, string|null> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        $directory = dirname(__DIR__, 2).'/var/sqlite-prod-test';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $this->databasePath = $directory.'/fewohbee.sqlite';
        $this->removeDatabase();

        // In operation these come from the service's environment file.
        $this->setEnv('FEWOHBEE_SQLITE_PATH', $this->databasePath);
        $this->setEnv('APP_SECRET', 'sqlite-prod-security-test-secret');
        $this->setEnv('TRUSTED_PROXIES', '127.0.0.1');
        // PHPUnit runs under the "cli" SAPI; the service runs "php -S" (cli-server), which
        // Symfony treats as web mode, e.g. for choosing the HTML error renderer.
        $this->setEnv('APP_RUNTIME_MODE', 'web=1');

        (new Filesystem())->remove(dirname(__DIR__, 2).'/var/cache/sqlite_prod');
        $this->kernel = new Kernel('sqlite_prod', false);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        self::assertSame(Command::SUCCESS, (new CommandTester($application->find('app:sqlite:init')))->execute([]));
        $firstRun = new CommandTester($application->find('app:first-run'));
        self::assertSame(Command::SUCCESS, $firstRun->execute([
            '--username' => 'demo-admin',
            '--password' => 'correct-demo-password',
            '--first-name' => 'Demo',
            '--last-name' => 'Admin',
            '--email' => 'demo-admin@example.test',
            '--accommodation-name' => 'Demo accommodation',
        ], ['interactive' => false]), $firstRun->getDisplay());
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach ($this->previousEnv as $name => $value) {
            if (null === $value) {
                unset($_SERVER[$name], $_ENV[$name]);
            } else {
                $_SERVER[$name] = $_ENV[$name] = $value;
            }
        }
        $this->removeDatabase();
    }

    public function testRequestsThroughTheTunnelAreSecureAndCarrySecurityHeaders(): void
    {
        $response = $this->handle(Request::create('https://'.self::HOST.'/login'));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));

        // A request that did not come through cloudflared cannot spoof the visitor.
        $direct = Request::create('http://'.self::HOST.'/login', server: ['REMOTE_ADDR' => '198.51.100.7']);
        $direct->headers->set('X-Forwarded-For', '192.0.2.1');
        $direct->headers->set('X-Forwarded-Proto', 'https');
        self::assertSame('198.51.100.7', $direct->getClientIp());
        self::assertFalse($direct->isSecure());
    }

    public function testErrorsDoNotRevealInternals(): void
    {
        // symfony/runtime only forces APP_DEBUG=false for environments listed here.
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertContains('sqlite_prod', $composer['extra']['runtime']['prod_envs'] ?? []);

        $response = $this->handle(Request::create('https://'.self::HOST.'/does-not-exist-'.bin2hex(random_bytes(4))));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringNotContainsString($this->kernel?->getProjectDir() ?? '/home/', $content);
        self::assertStringNotContainsStringIgnoringCase('stack trace', $content);
        self::assertStringNotContainsString('NotFoundHttpException', $content);
    }

    public function testRepeatedFailedLoginsAreThrottledPerVisitor(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $response = $this->login('wrong-password-'.$attempt);
            self::assertStringEndsWith('/login', (string) $response->headers->get('Location'), 'Attempt '.$attempt);
        }

        // Even the right password is refused while the visitor is throttled.
        $response = $this->login('correct-demo-password');
        self::assertStringEndsWith('/login', (string) $response->headers->get('Location'));
    }

    public function testCorrectLoginSucceedsWithoutPriorFailures(): void
    {
        $response = $this->login('correct-demo-password');
        self::assertStringEndsWith('/dashboard', (string) $response->headers->get('Location'));
    }

    private function login(string $password): Response
    {
        $request = Request::create('https://'.self::HOST.'/login', 'POST', [
            '_username' => 'demo-admin',
            '_password' => $password,
            '_csrf_token' => 'csrf-token',
        ]);
        // Same-origin form post; stateless CSRF validates the Origin header.
        $request->headers->set('Origin', 'https://'.self::HOST);

        return $this->handle($request);
    }

    /** Sends the request as cloudflared does: from 127.0.0.1 with forwarding headers. */
    private function handle(Request $request): Response
    {
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->server->set('HTTPS', '');
        $request->headers->set('X-Forwarded-For', self::VISITOR_IP);
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Host', self::HOST);

        self::assertNotNull($this->kernel);
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        // Trusted proxy handling must see the visitor and HTTPS.
        self::assertSame(self::VISITOR_IP, $request->getClientIp());
        self::assertTrue($request->isSecure());

        return $response;
    }

    private function setEnv(string $name, string $value): void
    {
        $this->previousEnv[$name] ??= $_SERVER[$name] ?? null;
        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    private function removeDatabase(): void
    {
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
