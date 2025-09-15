<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\TypePJ;

use Exception;
use SimpleXMLWrapper;
use UnrecoverableException;

class TypePJProvider
{
    /**
     * @throws Exception
     */
    public function getByNature(TypePJDTO $actesTypePJData): array
    {

        $simpleXMLWrapper = new SimpleXMLWrapper();

        $xml = $simpleXMLWrapper->loadFile($actesTypePJData->classificationFilePath);

        $all_type = [];
        foreach ($xml->xpath('//actes:TypePJNatureActe') as $type_pj) {
            $code = strval($type_pj->xpath('@actes:CodeTypePJ')[0]);
            $libelle = strval($type_pj->xpath('@actes:Libelle')[0]) . " ($code)";
            $nature_id = strval($type_pj->xpath('parent::actes:NatureActe/@actes:CodeNatureActe')[0]);
            $all_type[$nature_id][$code] = $libelle;
        }

        $result = $all_type;

        foreach ($result as $nature => $typologie_list) {
            $to_add = [];
            foreach ($typologie_list as $code => $libelle) {
                if (str_starts_with($code, '99_')) {
                    unset($result[$nature][$code]);
                    $to_add[$code] = $libelle;
                }
            }
            asort($result[$nature]);
            $result[$nature] = array_reverse($result[$nature]);
            foreach (array_reverse($to_add) as $code => $libelle) {
                $result[$nature][$code] = $libelle;
            }
            $result[$nature] = array_reverse($result[$nature]);
        }

        if (empty($result[$actesTypePJData->acteNature])) {
            throw new UnrecoverableException("La typologie n'est pas présente dans la classification");
        }

        return $result[$actesTypePJData->acteNature];
    }
}
