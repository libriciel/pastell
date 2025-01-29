<?php

class PESV2XMLFile
{
    public function getValueFromXPath(SimpleXMLElement $xml, $xpath_str)
    {
        $result = [];
        $attr_list = $xml->xpath($xpath_str);
        foreach ($attr_list as $attr) {
            $result[] = strval($attr);
        }
        $result = array_unique($result);
        return implode(', ', $result);
    }

    /**
     * @param $file_path
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function getSimpleXMLFromFile($file_path)
    {
        $simpleXMLWrapper = new SimpleXMLWrapper();
        return $simpleXMLWrapper->loadFile($file_path);
    }

    /**
     * @throws SimpleXMLWrapperException
     */
    public function getFromContent(string $xml): SimpleXMLElement
    {
        return (new SimpleXMLWrapper())->loadString($xml);
    }
}
