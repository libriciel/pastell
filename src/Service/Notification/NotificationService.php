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

    public function purgeIfNoAccess(int $id_u): void
    {
        foreach ($this->notification->getAll($id_u) as $notification) {
            $id_e = (int) $notification['id_e'];
            $type = (string) $notification['type'];
            if (!$this->canReceive($id_u, $id_e, $type)) {
                $this->notification->removeAll($id_u, $id_e, $type);
            }
        }
    }

    private function canReceive(int $id_u, int $id_e, string $type): bool
    {
        if (!$this->hasAccess($id_u, $id_e, DroitService::DROIT_ENTITE)) {
            return false;
        }
        if ($type === '' || $type === Notification::ALL_TYPE) {
            return true;
        }
        return $this->hasAccess($id_u, $id_e, $type);
    }

    private function hasAccess(int $id_u, int $id_e, string $droit): bool
    {
        try {
            return $this->droitService->hasDroitFor($id_u, $id_e, $droit, DroitType::LECTURE)
                || $this->droitService->hasDroitFor($id_u, $id_e, $droit, DroitType::EDITION);
        } catch (NotFoundException) {
            return false;
        }
    }
}
