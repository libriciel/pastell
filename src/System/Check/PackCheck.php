<?php

namespace Pastell\System\Check;

use Pastell\Service\Pack\PackService;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;

class PackCheck implements CheckInterface
{
    public function __construct(readonly PackService $packService)
    {
    }

    public function check(): array
    {
        $packs = [];
        $listPack = $this->packService->getListPack();
        if (empty($listPack)) {
            $packs[] = (new HealthCheckItem('Aucun pack installé', ''));
        } else {
            foreach ($this->packService->getListPack() as $packName => $packEnabled) {
                $packs[] = (new HealthCheckItem($packName, $packEnabled));
            }
        }
        return $packs;
    }
}
