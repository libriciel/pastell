<?php

/**
 * @deprecated 4.1.0, use SedaGenerator instead, to be removed in v5
 */
class SedaNGTEstBordereau extends ActionExecutor
{
    public function go()
    {
        /** @var SedaNG $sedaNG */
        $sedaNG = $this->getMyConnecteur();


        $result = $sedaNG->getBordereauTest();
        if (!$result) {
            $this->setLastMessage($sedaNG->getLastValidationError());
            return false;
        }

        header("Content-type: text/xml");
        header("Content-disposition: inline; filename=bordereau.xml");

        echo $result;
        exit;
    }
}
