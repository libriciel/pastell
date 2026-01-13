<?php

namespace Pastell\Service\SimpleTwigRenderer;

use SimpleXMLWrapper;
use SimpleXMLWrapperException;
use UnrecoverableException;

class SimpleTwigXpathCommon
{
    private static array $xmlCache = [];

    /**
     * @throws UnrecoverableException
     */
    public function doXpath($element_id, $xpath_expression, $donneesFormulaire)
    {
        if (!isset(self::$xmlCache[$element_id])) {
            try {
                $simpleXMLWrapper = new SimpleXMLWrapper();
                $filePath = $donneesFormulaire->getFilePath($element_id);
                if (!$filePath) {
                    throw new UnrecoverableException("Le fichier $element_id n'a pas été trouvé");
                }
                $xml = $simpleXMLWrapper->loadFile($filePath);
                // Removes the default namespace
                $xml = $simpleXMLWrapper->loadString(
                    preg_replace('/xmlns=(["\'])(?:(?!\1).)*\1/', '', $xml->asXML())
                );

                self::$xmlCache[$element_id] = $xml;
            } catch (SimpleXMLWrapperException) {
                throw new UnrecoverableException(
                    \sprintf(
                        "Le fichier %s n'est pas un fichier XML : impossible d'analyser l'expression xpath %s",
                        $element_id,
                        $xpath_expression
                    )
                );
            }
        }

        return self::$xmlCache[$element_id]->xpath($xpath_expression);
    }
}
