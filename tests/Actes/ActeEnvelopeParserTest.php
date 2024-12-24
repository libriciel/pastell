<?php

declare(strict_types=1);

namespace Pastell\Tests\Actes;

use Pastell\Actes\ActeEnvelopeParser;
use PHPUnit\Framework\TestCase;

class ActeEnvelopeParserTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testEnvelope(): void
    {
        $string = <<<XML
<?xml version="1.0" encoding="ISO-8859-1" ?>
<actes:Acte
xmlns:actes="http://www.interieur.gouv.fr/ACTES#v1.1-20040216"
xmlns:insee="http://xml.insee.fr/schema"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xsi:schemaLocation="http://www.interieur.gouv.fr/ACTES#v1.1-20040216 actesv1_1.xsd"
actes:Date="2024-12-09"
actes:NumeroInterne="20241209_MR02"
actes:CodeNatureActe="1">
 <actes:CodeMatiere1 actes:CodeMatiere="6"/>
 <actes:CodeMatiere2 actes:CodeMatiere="3"/>
 <actes:Objet>with 2 annexes</actes:Objet>
 <actes:ClassificationDateVersion>2019-08-29</actes:ClassificationDateVersion>
 <actes:Document>
  <actes:NomFichier>99_DE-034-000000000-20241209-20241209_MR02-DE-1-1_1.pdf</actes:NomFichier>
 </actes:Document>
 <actes:Annexes actes:Nombre="2">
  <actes:Annexe>
   <actes:NomFichier>99_SE-034-000000000-20241209-20241209_MR02-DE-1-1_2.pdf</actes:NomFichier>
  </actes:Annexe>
  <actes:Annexe>
   <actes:NomFichier>75_PL-034-000000000-20241209-20241209_MR02-DE-1-1_3.pdf</actes:NomFichier>
  </actes:Annexe>
 </actes:Annexes>
<actes:DocumentPapier>O</actes:DocumentPapier>
</actes:Acte>
XML;

        $xml = new ActeEnvelopeParser($string);

        self::assertSame('2024-12-09', $xml->getDate());
        self::assertSame('20241209_MR02', $xml->getNumeroInterne());
        self::assertSame('1', $xml->getCodeNatureActe());
        self::assertSame('6.3', $xml->getClassification());
        self::assertSame('with 2 annexes', $xml->getObjet());
        self::assertSame('99_DE-034-000000000-20241209-20241209_MR02-DE-1-1_1.pdf', $xml->getActeFilename());
        self::assertSame('2', $xml->getAnnexesNumber());
        self::assertSame('99_SE-034-000000000-20241209-20241209_MR02-DE-1-1_2.pdf', $xml->getAnnexeFilename(0));
        self::assertSame('75_PL-034-000000000-20241209-20241209_MR02-DE-1-1_3.pdf', $xml->getAnnexeFilename(1));
        self::assertTrue($xml->hasDocumentPapier());
    }

    public function getAractes(): string
    {
        return <<<XML
<?xml version="1.0"?>
<actes:ARActe xmlns:actes="http://www.interieur.gouv.fr/ACTES#v1.1-20040216" actes:IDActe="034-000000000-20241209-20241209_MR02-DE" actes:DateReception="2024-12-09">
  <actes:ActeRecu actes:Date="2024-12-09" actes:NumeroInterne="20241209_MR02" actes:CodeNatureActe="1">
    <actes:CodeMatiere1 actes:CodeMatiere="6"/>
    <actes:CodeMatiere2 actes:CodeMatiere="3"/>
    <actes:Objet>with 2 annexes</actes:Objet>
    <actes:ClassificationDateVersion>2019-08-29</actes:ClassificationDateVersion>
    <actes:Document>
      <actes:NomFichier>99_DE-034-000000000-20241209-20241209_MR02-DE-1-1_1.pdf</actes:NomFichier>
    </actes:Document>
    <actes:Annexes actes:Nombre="2">
      <actes:Annexe>
        <actes:NomFichier>/tmp/1268824286/99_SE-034-000000000-20241209-20241209_MR02-DE-1-1_2.pdf</actes:NomFichier>
      </actes:Annexe>
      <actes:Annexe>
        <actes:NomFichier>/tmp/1268824286/75_PL-034-000000000-20241209-20241209_MR02-DE-1-1_3.pdf</actes:NomFichier>
      </actes:Annexe>
    </actes:Annexes>
    <actes:DocumentPapier>O</actes:DocumentPapier>
  </actes:ActeRecu>
  <actes:ClassificationDateVersionEnCours>2019-08-29</actes:ClassificationDateVersionEnCours>
</actes:ARActe>
XML;
    }

    /**
     * @throws \Exception
     */
    public function testAractes(): void
    {
        $xml = new ActeEnvelopeParser($this->getAractes());

        self::assertSame('2024-12-09', $xml->getDateAr());
    }

    /**
     * @throws \Exception
     */
    public function testAttributeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Attribute 'Date' not found");
        $xml = new ActeEnvelopeParser($this->getAractes());

         $xml->getDate();
    }
}
