<?php

declare(strict_types=1);

namespace Mailsec;

use Mailsec\Exception\InvalidKeyException;
use Mailsec\Exception\UnavailableMailException;
use Mailsec\Exception\MissingPasswordException;
use Mailsec\Exception\NotEditableResponseException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

final class ExceptionListener implements EventSubscriberInterface
{
    public function __construct(private RouterInterface $router)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof MissingPasswordException) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate(
                        'mailsec_recipient_password',
                        ['key' => $event->getRequest()->query->get('key')],
                    ),
                )
            );
        }
        if ($exception instanceof InvalidKeyException) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate('mailsec_recipient_invalid')
                )
            );
        }
        if ($exception instanceof UnavailableMailException) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate('mailsec_recipient_unavailable')
                )
            );
        }
        if ($exception instanceof NotEditableResponseException) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate(
                        'mailsec_recipient_index',
                        ['key' => $event->getRequest()->query->get('key')],
                    ),
                )
            );
        }
    }
}
