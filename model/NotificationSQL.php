<?php

declare(strict_types=1);

class NotificationSQL extends SQL
{
    public function countSubscription(NotificationSubscription $subscription): int
    {
        $sql = <<<SQL
SELECT count(*) FROM notification
WHERE id_u = ? AND id_e = ? AND type = ? AND action = ?
SQL;
        return (int) $this->queryOne(
            $sql,
            $subscription->id_u,
            $subscription->id_e,
            $subscription->type,
            $subscription->action,
        );
    }

    public function insert(NotificationSubscription $subscription): void
    {
        $sql = <<<SQL
INSERT INTO notification(id_u, id_e, type, action, daily_digest) VALUES (?, ?, ?, ?, ?)
SQL;
        $this->query(
            $sql,
            $subscription->id_u,
            $subscription->id_e,
            $subscription->type,
            $subscription->action,
            (int) $subscription->daily_digest,
        );
    }

    /**
     * @return NotificationSubscription[]
     */
    public function getByUser(int $id_u): array
    {
        $sql = <<<SQL
SELECT notification.*, entite.denomination
FROM notification
LEFT JOIN entite ON notification.id_e = entite.id_e
WHERE id_u = ?
ORDER BY notification.id_n
SQL;
        return array_map(
            NotificationSubscription::fromArray(...),
            $this->query($sql, $id_u),
        );
    }

    public function getDailyDigestFlag(int $id_u, int $id_e, string $type): ?bool
    {
        $sql = <<<SQL
SELECT daily_digest FROM notification WHERE id_u = ? AND id_e = ? AND type = ? LIMIT 1
SQL;
        $result = $this->queryOne($sql, $id_u, $id_e, $type);
        return $result === false ? null : (bool) $result;
    }

    /**
     * @return NotificationSubscription[]
     */
    public function getByUserEntiteType(int $id_u, int $id_e, string $type): array
    {
        $sql = <<<SQL
SELECT * FROM notification WHERE id_u = ? AND id_e = ? AND type = ?
SQL;
        return array_map(
            NotificationSubscription::fromArray(...),
            $this->query($sql, $id_u, $id_e, $type),
        );
    }

    public function getById(int $id_n): ?NotificationSubscription
    {
        $sql = <<<SQL
SELECT * FROM notification WHERE id_n = ?
SQL;
        $row = $this->queryOne($sql, $id_n);
        return $row ? NotificationSubscription::fromArray($row) : null;
    }

    /**
     * @return NotificationSubscription[]
     */
    public function getMatchingSubscriptions(int $id_e, string $type, string $action, bool $onlyEnabled): array
    {
        $sql = <<<SQL
SELECT notification.*, utilisateur.email
FROM notification
JOIN utilisateur ON notification.id_u = utilisateur.id_u
WHERE (notification.id_e = ? OR notification.id_e = 0)
  AND (type = ? OR type = '0')
  AND (action = ? OR action = '0')
SQL;
        if ($onlyEnabled) {
            $sql .= "\n  AND is_enabled = 1";
        }
        return array_map(
            NotificationSubscription::fromArray(...),
            $this->query($sql, $id_e, $type, $action),
        );
    }

    public function deleteByUserEntiteType(int $id_u, int $id_e, string $type): void
    {
        $sql = <<<SQL
DELETE FROM notification WHERE id_u = ? AND id_e = ? AND type = ?
SQL;
        $this->query($sql, $id_u, $id_e, $type);
    }

    public function toggleDailyDigest(int $id_u, int $id_e, string $type): void
    {
        $sql = <<<SQL
UPDATE notification SET daily_digest = 1 - daily_digest WHERE id_u = ? AND id_e = ? AND type = ?
SQL;
        $this->query($sql, $id_u, $id_e, $type);
    }

    public function deleteByEntite(int $id_e): void
    {
        $sql = <<<SQL
DELETE FROM notification WHERE id_e = ?
SQL;
        $this->query($sql, $id_e);
    }

    public function deleteByUser(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM notification WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }
}
