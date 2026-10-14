<?php

declare(strict_types=1);

namespace Pastell\Service\Notification;

use NotificationSQL;
use NotificationSubscription;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use UtilisateurRoleSQL;

class NotificationService
{
    public function __construct(
        private readonly NotificationSQL $notificationSQL,
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
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
        return $this->notificationSQL->getMatchingSubscriptions($id_e, $type, $action);
    }

    private function hasDroit(array $droits, string $type): bool
    {
        return \in_array(DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE), $droits, true)
            && (
                $type === NotificationSubscription::ALL_TYPE
                || \in_array(DroitService::getDroitFor($type, DroitType::LECTURE), $droits, true)
            );
    }

    public function removeAllForUser(int $id_u): void
    {
        $this->notificationSQL->deleteByUser($id_u);
    }

    public function removeAllForEntite(int $id_e): void
    {
        $this->notificationSQL->deleteByEntite($id_e);
    }

    public function revokeInvalid(int $id_u): void
    {
        $droits = [];
        foreach ($this->getSubscriptionsByUser($id_u) as $subscription) {
            $id_e = $subscription['id_e'];
            $droits[$id_e] ??= $this->utilisateurRoleSQL->getDroitsForEntite($id_u, $id_e);
            if (!$this->hasDroit($droits[$id_e], $subscription['type'])) {
                $this->notificationSQL->deleteByUserEntiteType($id_u, $id_e, $subscription['type']);
            }
        }
    }

    public function revokeInvalidForRole(string $role): void
    {
        foreach ($this->utilisateurRoleSQL->getSubscribedUsersByRole($role) as $id_u) {
            $this->revokeInvalid($id_u);
        }
    }
}
