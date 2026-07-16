<?php

declare(strict_types=1);

namespace Pastell\Tests\Step;

use DonneesFormulaire;
use Pastell\Step\AnnexeList;
use PHPUnit\Framework\TestCase;

class AnnexeListTest extends TestCase
{
    /**
     * @return \Generator<string, array{array<string, mixed>, string[]}>
     */
    public function mappingProvider(): \Generator
    {
        yield 'un seul élément' => [['autre_document_attache' => 'annexe'], ['annexe']];
        yield 'plusieurs éléments' => [
            ['autre_document_attache' => ['autre_document_a_signer', 'annexe']],
            ['autre_document_a_signer', 'annexe'],
        ];
        yield 'mapping absent : repli sur un élément du même nom que la clé' => [
            ['arrete' => 'document'],
            ['autre_document_attache'],
        ];
        yield 'mapping vide : repli sur un élément du même nom que la clé' => [
            ['autre_document_attache' => ''],
            ['autre_document_attache'],
        ];
        yield 'les éléments vides sont ignorés' => [
            ['autre_document_attache' => ['annexe', '']],
            ['annexe'],
        ];
    }

    /**
     * @dataProvider mappingProvider
     * @param array<string, mixed> $mapping
     * @param string[] $expected
     */
    public function testGetElements(array $mapping, array $expected): void
    {
        self::assertSame($expected, AnnexeList::getElements($mapping));
    }

    public function testGetAllMetLesAnnexesAPlatDansLOrdreDesElements(): void
    {
        $donneesFormulaire = $this->createMock(DonneesFormulaire::class);
        $donneesFormulaire->method('get')->willReturnMap([
            ['autre_document_a_signer', false, ['convention.pdf']],
            ['annexe', false, ['annexe1.pdf', 'annexe2.pdf']],
        ]);

        self::assertSame(
            [
                ['element' => 'autre_document_a_signer', 'num' => 0, 'filename' => 'convention.pdf'],
                ['element' => 'annexe', 'num' => 0, 'filename' => 'annexe1.pdf'],
                ['element' => 'annexe', 'num' => 1, 'filename' => 'annexe2.pdf'],
            ],
            AnnexeList::getAll(['autre_document_a_signer', 'annexe'], $donneesFormulaire)
        );
    }

    public function testGetAllIgnoreLesElementsSansFichier(): void
    {
        $donneesFormulaire = $this->createMock(DonneesFormulaire::class);
        $donneesFormulaire->method('get')->willReturnMap([
            ['autre_document_a_signer', false, false],
            ['annexe', false, ['annexe1.pdf']],
        ]);

        self::assertSame(
            [['element' => 'annexe', 'num' => 0, 'filename' => 'annexe1.pdf']],
            AnnexeList::getAll(['autre_document_a_signer', 'annexe'], $donneesFormulaire)
        );
    }

    /**
     * Un fichier supprimé laisse un trou dans les index de l'élément : c'est le numéro réel
     * qui doit être conservé, pas la position dans la liste à plat.
     */
    public function testGetAllConserveLesNumerosDeFichier(): void
    {
        $donneesFormulaire = $this->createMock(DonneesFormulaire::class);
        $donneesFormulaire->method('get')->willReturnMap([
            ['annexe', false, [0 => 'annexe1.pdf', 2 => 'annexe3.pdf']],
        ]);

        self::assertSame(
            [
                ['element' => 'annexe', 'num' => 0, 'filename' => 'annexe1.pdf'],
                ['element' => 'annexe', 'num' => 2, 'filename' => 'annexe3.pdf'],
            ],
            AnnexeList::getAll(['annexe'], $donneesFormulaire)
        );
    }

    public function testGetFilenames(): void
    {
        $donneesFormulaire = $this->createMock(DonneesFormulaire::class);
        $donneesFormulaire->method('get')->willReturnMap([
            ['autre_document_a_signer', false, ['convention.pdf']],
            ['annexe', false, ['annexe1.pdf', 'annexe2.pdf']],
        ]);

        self::assertSame(
            ['convention.pdf', 'annexe1.pdf', 'annexe2.pdf'],
            AnnexeList::getFilenames(['autre_document_a_signer', 'annexe'], $donneesFormulaire)
        );
    }
}
