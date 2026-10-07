<?php

declare(strict_types=1);

namespace Pastell\Service\Notification;

use NotificationSQL;
use NotificationSubscription;

class NotificationService
{
    public function __construct(
        private readonly NotificationSQL $notificationSQL,
    ) {
    }

    public function subscribe(int $id_u, int $id_e, string $type, string $action, bool $dailyDigest): void
    {
        $subscription = new NotificationSubscription($id_u, $id_e, $type, $action, $dailyDigest);
        if ($this->notificationSQL->countSubscription($subscription) > 0) {
            return;
        }
        $this->notificationSQL->insert($subscription);
    }

    /**
     * @return array<string, array>
     */
    public function getSubscriptionsByUser(int $id_u): array
    {
        $result = [];
        foreach ($this->notificationSQL->getByUser($id_u) as $subscription) {
            $key = $subscription->id_e . '-' . $subscription->type;
            if (!isset($result[$key])) {
                $result[$key] = [
                    'id_n' => $subscription->id_n,
                    'id_u' => $subscription->id_u,
                    'id_e' => $subscription->id_e,
                    'type' => $subscription->type,
                    'action' => [],
                    'daily_digest' => (int) $subscription->daily_digest,
                    'denomination' => $subscription->denomination,
                ];
            }
            $result[$key]['action'][] = $subscription->action;
        }
        return $result;
    }

    public function hasDailyDigest(int $id_u, int $id_e, string $type): bool
    {
        return (bool) $this->notificationSQL->getDailyDigestFlag($id_u, $id_e, $type);
    }

    public function setSubscriptionStateOnActionList(int $id_u, int $id_e, string $type, array $actionList): array
    {
        $subscribedActions = [];
        foreach ($this->notificationSQL->getByUserEntiteType($id_u, $id_e, $type) as $subscription) {
            $subscribedActions[$subscription->action] = true;
        }
        foreach ($actionList as $i => $action) {
            $actionList[$i]['checked'] = isset($subscribedActions[$action['id']]);
        }
        return $actionList;
    }

    public function getSubscription(int $id_n): ?NotificationSubscription
    {
        return $this->notificationSQL->getById($id_n);
    }

    public function unsubscribeAll(int $id_u, int $id_e, string $type): void
    {
        $this->notificationSQL->deleteByUserEntiteType($id_u, $id_e, $type);
    }

    public function toggleDailyDigest(int $id_u, int $id_e, string $type): void
    {
        $this->notificationSQL->toggleDailyDigest($id_u, $id_e, $type);
    }

    /**
     * @return NotificationSubscription[]
     */
    public function getRecipients(int $id_e, string $type, string $action): array
    {
        return $this->notificationSQL->getMatchingSubscriptions($id_e, $type, $action, true);
    }

    /**
     * @return string[]
     */
    public function getRecipientEmails(int $id_e, string $type, string $action): array
    {
        return array_values(array_filter(array_map(
            static fn(NotificationSubscription $recipient): ?string => $recipient->email,
            $this->getRecipients($id_e, $type, $action),
        )));
    }

    public function removeAllForUser(int $id_u): void
    {
        $this->notificationSQL->deleteByUser($id_u);
    }

    public function removeAllForEntite(int $id_e): void
    {
        $this->notificationSQL->deleteByEntite($id_e);
    }
}
