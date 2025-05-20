<?php

class DepotCMISDepotXML extends ActionExecutor
{
    public function go()
    {
        /** @var DepotConnecteur $depotConnecteur */
        $depotConnecteur = $this->getMyConnecteur();

        $filename = 'test_file_' . mt_rand(0, mt_getrandmax()) . ".xml";

        $result = $depotConnecteur->saveDocument(
            "",
            $filename,
            __DIR__ . "/../fixtures/test.xml"
        );

        $this->setLastMessage("Dépot du fichier sur : $result");
        return true;
    }
}
