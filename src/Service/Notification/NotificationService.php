<?php

declare(strict_types=1);

namespace Pastell\Service\Notification;

use NotFoundException;
use Notification;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

final class NotificationService
{
    public function __construct(
        private readonly Notification $notification,
        private readonly DroitService $droitService,
    ) {
    }

    /**
     * @throws NotFoundException
     */
    public function purgeIfNoAccess(int $id_u, int $id_e): void
    {
        if ($this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_ENTITE, DroitType::LECTURE)) {
            return;
        }
        $this->notification->removeAllForUserAndEntite($id_u, $id_e);
    }
}
