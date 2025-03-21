<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use ActionExecutor;
use Exception;
use IparapheurRest;
use Pastell\Connector\IparapheurRest\IpRestException;

class IpRestDisplaySubType extends ActionExecutor
{
    /**
     * @throws IpRestException
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var IparapheurRest $connector */
        $connector = $this->getMyConnecteur();
        $properties = $this->getConnecteurProperties();
        $subTypeList = $connector->getSubTypeList();

        $message = sprintf(
            'Liste des sous-types pour le type %s : %s',
            $properties->get('iparapheur_type'),
            implode(', ', $subTypeList)
        );
        $this->setLastMessage($message);
        return true;
    }
}
