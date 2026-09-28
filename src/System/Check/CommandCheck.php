<?php

namespace Pastell\System\Check;

use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;
use VerifEnvironnement;

class CommandCheck implements CheckInterface
{
    /**
     * @var VerifEnvironnement
     */
    private $verifEnvironnement;

    public function __construct(VerifEnvironnement $verifEnvironnement)
    {
        $this->verifEnvironnement = $verifEnvironnement;
    }

    public function check(): array
    {
        $commands = [];
        foreach ($this->verifEnvironnement->checkCommande(['xmlstarlet']) as $command => $path) {
            $commands[] = (new HealthCheckItem(
                $command,
                $path ?: "La commande n'est pas disponible"
            ))->setSuccess((bool)$path);
        }
        return $commands;
    }
}
