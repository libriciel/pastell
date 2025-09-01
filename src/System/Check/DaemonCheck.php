<?php

declare(strict_types=1);

namespace Pastell\System\Check;

use Daemon;
use DaemonManager;
use DaemonSQL;
use EntiteSQL;
use JobQueueSQL;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;
use UnrecoverableException;

class DaemonCheck implements CheckInterface
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
        private readonly EntiteSQL $entiteSQL
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function check(): array
    {
        return [$this->checkDaemon()];
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkDaemon(): HealthCheckItem
    {
        $daemons = $this->daemonSQL->getAllDaemons();
        $globalSuccess = true;
        $details = [];

        foreach ($daemons as $daemon) {
            /** @var Daemon $daemon */
            $success = true;
            $lastTry = $this->jobQueueSQL->getMaxLastTryOneHourLate($daemon->id_daemon);

            $message = '';
            if ($lastTry && (time() - strtotime($lastTry) > 3600)) {
                $late_jobs = $this->jobQueueSQL->getLateJobs($daemon->id_daemon);
                $nbLateJobs = count($late_jobs);
                $etat = ($nbLateJobs > 1) ? 'travaux ont' : 'travail a';
                $message .= "$nbLateJobs $etat plus d'une heure de retard. \n";
                if ($nbLateJobs >= $daemon->late_jobs_threshold) {
                    $success = false;
                    $globalSuccess = false;
                }
            }

            $nbLock = $this->jobQueueSQL->getNbLockSinceOneHourForDaemon($daemon->id_daemon);
            if ($nbLock) {
                $etat = ($nbLock > 1) ? 'travaux sont suspendus' : 'travail est suspendu';
                $message .= "$nbLock $etat depuis plus d'une heure. \n";
                $success = false;
                $globalSuccess = false;
            }

            if ($success) {
                $message .= "Le gestionnaire des tâches fonctionne correctement. \n";
            }

            $daemon_context = $daemon->toArray();
            $daemon_context['denomination_entite'] = $daemon->id_daemon === DaemonSQL::GLOBAL_DAEMON ?
                'Gestionnaire global' :
                $this->entiteSQL->getDenomination($daemon->id_e);

            $item = new HealthCheckItem((string)$daemon->id_daemon, $message)
                ->setSuccess($success)
                ->setContext($daemon_context);

            $details[] = $item;
        }

        return new HealthCheckItem('Tâches automatiques', 'Vérification des gestionnaires de tâches.')
            ->setSuccess($globalSuccess)
            ->setDetails($details);
    }
}
