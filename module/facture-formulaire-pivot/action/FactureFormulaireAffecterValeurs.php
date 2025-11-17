<?php

/**
 * @deprecated 5.0.0 Chorus
 */

class FactureFormulaireAffecterValeurs extends ActionExecutor
{
    protected function metier()
    {
        /** @var ParametrageFluxFacturePivot $conn */
        $conn = $this->getConnecteur('ParametrageFluxFacturePivot');
        $doc = $this->getDonneesFormulaire();

        $result = '';
        $tabParam = $conn->getParametres();
        foreach ($tabParam as $param => $value) {
            if (!$doc->get($param)) {
                $doc->setData($param, $value);
                $result .= '[ ' . $param . ' = ' . $value . ' ]';
            }
        }
        return $result;
    }

    public function go()
    {
        try {
            $result = $this->metier();
            $this->setLastMessage($result);
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
