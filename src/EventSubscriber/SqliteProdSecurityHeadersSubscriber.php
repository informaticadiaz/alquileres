<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Baseline security headers for the publicly exposed SQLite profile.
 *
 * Runs after EmbeddingSecuritySubscriber: responses that already declare CSP
 * frame-ancestors (the embeddable /book routes) keep that policy instead of DENY.
 */
#[When(env: 'sqlite_prod')]
final class SqliteProdSecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $headers->get('Content-Security-Policy', '');
        if (!str_contains($csp, 'frame-ancestors') && !$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }
    }
}
