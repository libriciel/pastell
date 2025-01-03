<?php

namespace Pastell\Service\Entite;

use DaemonManager;
use DaemonSQL;
use EntiteSQL;
use Journal;
use UnrecoverableException;

class EntiteDeletionService
{
    /**
     * @var EntiteSQL
     */
    private $entiteSQL;
    private DaemonSQL $daemonSQL;
    private DaemonManager $daemonManager;

    /**
     * @var Journal
     */
    private $journal;

    public function __construct(EntiteSQL $entiteSQL, Journal $journal, DaemonSQL $daemonSQL, DaemonManager $daemonManager)
    {
        $this->entiteSQL = $entiteSQL;
        $this->journal = $journal;
        $this->daemonSQL = $daemonSQL;
        $this->daemonManager = $daemonManager;
    }

    /**
     * @param int $id_e
     * @throws UnrecoverableException
     */
    public function delete(int $id_e): void
    {
        $info = $this->entiteSQL->getInfo($id_e);
        $this->entiteSQL->removeEntite($id_e);
        $daemon = $this->daemonSQL->getDaemonByEntity($id_e);
        if ($daemon !== null) {
            $this->daemonManager->removeDaemon($daemon->id_daemon);
        }
        $this->journal->add(
            Journal::MODIFICATION_ENTITE,
            $id_e,
            Journal::NO_ID_D,
            Journal::ACTION_SUPPRIME,
            "Suppression de l'entité id_e=$id_e\nInformation : " . json_encode($info)
        );
    }
}
