<?php

/**
 * Class UTF8Encoder
 *
 * La fonction utf8_encode_array (coeur Pastell 1.4) ne conserve pas les types des éléments du tableau
 * Cela pose un problème lorsque l'on doit encoder le tableau en json
 *
 *
 */

class UTF8Encoder
{
    public function encode($var)
    {
        if ($this->isScalar($var)) {
            return $this->encodeScalar($var);
        }
        $result = [];
        foreach ($var as $key => $value) {
            $result[$this->encodeScalar($key)] = $this->encode($value);
        }
        return $result;
    }

    private function isScalar($var)
    {
        return ! (is_array($var) || is_object($var));
    }

    public function encodeScalar($scalar)
    {
        if (is_string($scalar)) {
            $scalar = mb_convert_encoding($scalar, 'UTF-8', 'ISO-8859-1');
        }
        return $scalar;
    }

    //EP : Houlala, c'est chaud ça !!!! Peut-être une erreur suite au changement d'encodage de Pastell
    public function decodeScalar($scalar)
    {
        /*if (is_string($scalar)){
            $scalar = utf8_decode($scalar);
        }*/
        return $scalar;
    }

    public function decode($var)
    {
        if ($this->isScalar($var)) {
            return $this->decodeScalar($var);
        }
        $result = [];
        foreach ($var as $key => $value) {
            $result[$this->decodeScalar($key)] = $this->decode($value);
        }
        return $result;
    }
}
