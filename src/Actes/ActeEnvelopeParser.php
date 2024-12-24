<?php

declare(strict_types=1);

namespace Pastell\Actes;

use SimpleXMLElement;

final class ActeEnvelopeParser
{
    private const ACTES_NAMESPACE = 'http://www.interieur.gouv.fr/ACTES#v1.1-20040216';
    private readonly SimpleXMLElement $xml;

    /**
     * @throws \Exception
     */
    public function __construct(string $xmlContent)
    {
        $this->xml = new SimpleXMLElement($xmlContent);
        $this->xml->registerXPathNamespace('actes', self::ACTES_NAMESPACE);
    }

    private function getAttribute(string $attribute): string
    {
        $attributes = $this->xml->attributes(self::ACTES_NAMESPACE);
        if ($attributes === null || !isset($attributes[$attribute])) {
            throw new \RuntimeException("Attribute '$attribute' not found");
        }
        return (string)$attributes[$attribute];
    }

    private function getValueFromXpath(string $expression, int $index = 0): string
    {
        $object = $this->xml->xpath($expression);
        if (\is_array($object)) {
            if (\count($object) <= $index) {
                throw new \OutOfBoundsException(
                    "Element number $index does not exist for XPath expression: $expression"
                );
            }
            return (string)$object[$index];
        }
        throw new \RuntimeException("No value found for XPath expression: $expression");
    }

    public function getDate(): string
    {
        return $this->getAttribute('Date');
    }

    public function getNumeroInterne(): string
    {
        $num = $this->getAttribute('NumeroInterne');
        $num = str_replace('-', '_', $num);
        $num = strtoupper($num);
        return $num;
    }

    public function getCodeNatureActe(): string
    {
        return $this->getAttribute('CodeNatureActe');
    }

    public function getClassification(int $depth = 5): string
    {
        $classif = [];
        for ($i = 1; $i <= $depth; ++$i) {
            $expression = \sprintf('//actes:CodeMatiere%d/@actes:CodeMatiere', $i);
            try {
                $classif[] = $this->getValueFromXpath($expression);
            } catch (\OutOfBoundsException) {
            }
        }

        return \implode('.', $classif);
    }

    public function getObjet(): string
    {
        return $this->getValueFromXpath('//actes:Objet');
    }

    public function getActeFilename(): string
    {
        return $this->getValueFromXpath('//actes:Document/actes:NomFichier');
    }

    public function getAnnexesNumber(): string
    {
        return $this->getValueFromXpath('//actes:Annexes/@actes:Nombre');
    }

    public function getAnnexeFilename(int $index = 0): string
    {
        return $this->getValueFromXpath('//actes:Annexe/actes:NomFichier', $index);
    }

    public function hasDocumentPapier(): bool
    {
        return $this->getValueFromXpath('//actes:DocumentPapier') === 'O';
    }

    public function getDateAr(): string
    {
        return $this->getAttribute('DateReception');
    }
}
