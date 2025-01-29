<?php

class PESAllerFile extends PESV2XMLFile
{
    public const ID_COLL = 'IdColl';
    public const DTE_STR = 'DteStr';
    public const COD_BUD = 'CodBud';
    public const EXERCICE = 'Exercice';
    public const ID_BORD = 'IdBord';
    public const MT_BORD_HT = 'MtBordHt';
    public const ID_PJ = 'IdPJ';
    public const ID_PCE = 'IdPce';
    public const NOM_FIC = 'NomFic';
    public const ID_NATURE = 'IdNature';
    public const ID_FONCTION = 'IdFonction';
    public const LIBELLE_COD_BUD = 'LibelleColBud';

    /**
     * @param $pes_aller_path
     * @return mixed
     * @throws Exception
     */
    public function getAllInfo($pes_aller_path, bool $getFromPath = true)
    {
        if ($getFromPath) {
            $xml = $this->getSimpleXMLFromFile($pes_aller_path);
        } else {
            $xml = $this->getFromContent($pes_aller_path);
        }

        $info[self::ID_COLL] = $this->getValueFromXPath($xml, "//EnTetePES/IdColl/@V");
        $info[self::DTE_STR] = $this->getValueFromXPath($xml, "//EnTetePES/DteStr/@V");
        $info[self::COD_BUD] = $this->getValueFromXPath($xml, "//EnTetePES/CodBud/@V");

        $info[self::LIBELLE_COD_BUD] = $this->getValueFromXPath($xml, "//EnTetePES/LibelleColBud/@V");

        $info[self::EXERCICE] = $this->getValueFromXPath(
            $xml,
            "//Bordereau/BlocBordereau/Exer/@V|//PES_PJ/PJ/RefCompta/Exercice/@V"
        );
        $info[self::ID_BORD] = $this->getValueFromXPath($xml, "//Bordereau/BlocBordereau/IdBord/@V");
        $info[self::ID_PJ] = $this->getValueFromXPath($xml, "//PES_PJ/PJ/IdUnique/@V");
        $info[self::ID_PCE] = $this->getValueFromXPath($xml, "//Bordereau/Piece/BlocPiece/InfoPce/IdPce/@V");
        $info[self::NOM_FIC] = $this->getValueFromXPath($xml, "//Enveloppe/Parametres/NomFic/@V");

        // PesDepense => MtBordHT et PesRecette => MtBordHt
        $info[self::MT_BORD_HT] = $this->getValueFromXPath($xml, "//Bordereau/BlocBordereau/MtBordHT/@V");
        if (! $info[self::MT_BORD_HT]) {
            $info[self::MT_BORD_HT] = $this->getValueFromXPath($xml, "//Bordereau/BlocBordereau/MtBordHt/@V");
        }

        // PesDepense => InfoLignePce et PesRecette => InfoLignePiece
        $info[self::ID_NATURE] = $this->getValueFromXPath(
            $xml,
            "//Bordereau/Piece/LigneDePiece/BlocLignePiece/InfoLignePce/Nature/@V"
        );
        if (! $info[self::ID_NATURE]) {
            $info[self::ID_NATURE] = $this->getValueFromXPath(
                $xml,
                "//Bordereau/Piece/LigneDePiece/BlocLignePiece/InfoLignePiece/Nature/@V"
            );
        }

        // PesDepense => InfoLignePce et PesRecette => InfoLignePiece
        $info[self::ID_FONCTION] = $this->getValueFromXPath(
            $xml,
            "//Bordereau/Piece/LigneDePiece/BlocLignePiece/InfoLignePce/Fonction/@V"
        );
        if (! $info[self::ID_FONCTION]) {
            $info[self::ID_FONCTION] = $this->getValueFromXPath(
                $xml,
                "//Bordereau/Piece/LigneDePiece/BlocLignePiece/InfoLignePiece/Fonction/@V"
            );
        }

        return $info;
    }
}
