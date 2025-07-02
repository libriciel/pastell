<?php

declare(strict_types=1);

namespace Pastell\System\Check;

use Daemon;
use DaemonSQL;
use EntiteSQL;
use JobQueueSQL;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;

class DaemonCheck implements CheckInterface
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
        private readonly EntiteSQL $entiteSQL
    ) {
    }

    public function check(): array
    {
        return [$this->checkDaemon()];
    }

    private function checkDaemon(): HealthCheckItem
    {
        $daemons = $this->daemonSQL->getAllDaemons();
        $globalSuccess = true;
        $details = [];
        foreach ($daemons as $daemon) {
            /** @var Daemon $daemon */
            $success = true;
            $lastTry = $this->jobQueueSQL->getMaxLastTryOneHourLate($daemon->id_daemon);

            if ($lastTry && (time() - strtotime($lastTry) > 3600)) {
                $message = "Le gestionnaire des tâches semble arrêté depuis plus d'une heure.";
                $success = false;
                $globalSuccess = false;
            } else {
                $nbLock = $this->jobQueueSQL->getNbLockSinceOneHourForDaemon($daemon->id_daemon);
                if ($nbLock) {
                    $etat = ($nbLock > 1) ? 'travaux sont suspendus' : 'travail est suspendu';
                    $message = "{$nbLock} {$etat} depuis plus d'une heure.";
                    $success = false;
                    $globalSuccess = false;
                } else {
                    $message = 'Le gestionnaire des tâches fonctionne correctement.';
                }
            }

            $daemon_context = $daemon->toArray();
            $daemon_context['denomination_entite'] = $daemon->id_daemon === DaemonSQL::GLOBAL_DAEMON ?
                'Gestionnaire global' :
                $this->entiteSQL->getDenomination($daemon->id_e);

            $item = (new HealthCheckItem((string)$daemon->id_daemon, $message))
                ->setSuccess($success)
                ->setContext($daemon_context);

            $details[] = $item;
        }

        return (new HealthCheckItem('Tâches automatiques', 'Vérification des daemons.'))
            ->setSuccess($globalSuccess)
            ->setDetails($details);
    }
}
