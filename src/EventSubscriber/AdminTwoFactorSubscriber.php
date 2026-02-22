<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
class AdminTwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // protège seulement /admin
        if (!str_starts_with($path, '/admin')) {
            return;
        }

        // laisse passer la page PIN
        if ($path === '/admin/pin' || $path === '/admin/pin/resend') {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) return;

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $verified = (bool) $request->getSession()->get('admin_2fa_verified', false);
            if (!$verified) {
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_pin')));
            }
        }
    }
}
