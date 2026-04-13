<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\MercurePublisher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final readonly class MercureTerminateListener
{
    public function __construct(
        private MercurePublisher $mercurePublisher,
    ) {}

    #[AsEventListener(event: RequestEvent::class, priority: 255)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->mercurePublisher->startDeferring();
        }
    }

    #[AsEventListener(event: TerminateEvent::class)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->mercurePublisher->flushDeferred();
    }
}
