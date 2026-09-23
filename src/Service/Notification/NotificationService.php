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

    public function getAllForUser(int $id_u): array
    {
        return $this->notification->getAll($id_u);
    }

    public function getInfo(int $id_n): mixed
    {
        return $this->notification->getInfo($id_n);
    }

    public function hasDailyDigest(int $id_u, int $id_e, string $type): mixed
    {
        return $this->notification->hasDailyDigest($id_u, $id_e, $type);
    }

    public function getActionList(int $id_u, int $id_e, string $type, array $action_list): array
    {
        return $this->notification->getNotificationActionList($id_u, $id_e, $type, $action_list);
    }

    public function subscribe(int $id_u, int $id_e, string $type, int $daily_digest): void
    {
        $this->notification->add($id_u, $id_e, $type, Notification::ALL_TYPE, $daily_digest);
    }

    public function setActions(
        int $id_u,
        int $id_e,
        string $type,
        array $checked_actions,
        bool $all_checked,
        int $daily_digest,
    ): void {
        $this->notification->removeAll($id_u, $id_e, $type);
        if (!$checked_actions) {
            return;
        }
        if ($all_checked) {
            $this->notification->add($id_u, $id_e, $type, Notification::ALL_TYPE, $daily_digest);
            return;
        }
        foreach ($checked_actions as $action) {
            $this->notification->add($id_u, $id_e, $type, $action, $daily_digest);
        }
    }

    public function unsubscribe(int $id_u, int $id_e, string $type): void
    {
        $this->notification->removeAll($id_u, $id_e, $type);
    }

    public function toggleDailyDigest(int $id_u, int $id_e, string $type): void
    {
        $this->notification->toogleDailyDigest($id_u, $id_e, $type);
    }
}
