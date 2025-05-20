<?php

namespace Pastell\Tests\Command;

use ConnecteurFactory;
use DonneesFormulaireFactory;
use Exception;
use InvalidArgumentException;
use Journal;
use NotFoundException;
use Pastell\Command\ForceUpdateField;
use Pastell\Service\UpdateFieldService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use UnrecoverableException;

final class ForceUpdateFieldTest extends PastellTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $command = new ForceUpdateField(
            $this->getObjectInstancier()->getInstance(UpdateFieldService::class),
            $this->getObjectInstancier()->getInstance(Journal::class)
        );
        $this->commandTester = new CommandTester($command);
    }

    private function executeCommand(
        string $scope,
        string $type,
        string $field,
        string $twigExpression,
        string $confirm
    ): int {
        $this->commandTester->setInputs([$confirm]);
        return $this->commandTester->execute(
            [
                'scope' => $scope,
                'type' => $type,
                'field' => $field,
                'twigExpression' => $twigExpression,
            ]
        );
    }

    public function commandArgumentsProvider(): iterable
    {
        yield [
            'dossiers',
            'module',
            'test',
            'nom',
            '{% if nom == "" %}NouveauNom{% else %}{{nom}}{% endif %}',
            'yes',
            'UnNom',
            1
        ];
        yield [
            'dossiers',
            'module',
            'test',
            'prenom',
            '{% if prenom == "" %}NouveauPrenom{% else %}{{prenom}}{% endif %}',
            'yes',
            'NouveauPrenom',
            1
        ];
        yield [
            'configuration',
            'connector',
            'test',
            'champs1',
            '{% if champs2 == "" %}NouvelleValeur{% else %}{{champs2}}{% endif %}',
            'yes',
            'NouvelleValeur',
            3
        ];
    }

    /**
     * @dataProvider commandArgumentsProvider
     * @throws NotFoundException
     * @throws Exception
     */
    public function testCommand(
        string $scopeType,
        string $scope,
        string $type,
        string $field,
        string $twigExpression,
        string $confirm,
        string $newValue,
        int $documentsNumber
    ): void {

        if ($scope === UpdateFieldService::SCOPE_MODULE) {
            $document = $this->createDocument('test');
            $this->configureDocument($document['id_d'], ['nom' => 'UnNom']);
        } else {
            $document = $this->createConnector('test', 'Connecteur Test entité');
        }

        $this->assertSame(
            0,
            $this->executeCommand($scope, $type, $field, $twigExpression, $confirm)
        );

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0/' . $documentsNumber, $output);
        $this->assertStringContainsString($documentsNumber . '/' . $documentsNumber, $output);

        if ($scope === UpdateFieldService::SCOPE_MODULE) {
            $donneesFormulaire = $this->getObjectInstancier()
                ->getInstance(DonneesFormulaireFactory::class)
                ->get($document['id_d']);
        } else {
            $donneesFormulaire = $this->getObjectInstancier()
                ->getInstance(ConnecteurFactory::class)
                ->getConnecteurConfig($document['id_ce']);
        }
        $this->assertSame(
            $newValue,
            $donneesFormulaire->get($field)
        );

        $this->assertEquals(
            sprintf(
                '`app:force-update-field` Update field `%s` by twig expression `%s` for %s %s %s `%s`',
                $field,
                $twigExpression,
                $documentsNumber,
                $scopeType,
                $scope,
                $type
            ),
            $this->getJournal()->getAll()[0]['message']
        );
    }

    public function testCommandInvalidTwig(): void
    {
        $this->createConnector('test', 'Connecteur Test entité');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Erreur de syntaxe sur le template twig ligne 1<br />
Message d\'erreur : Unexpected token "end of template" of value "".<br />
<br />
<br />
<br />
<b>1. {% if champs2 == "" %</b><em>^^^ Unexpected token "end of template" of value "".</em><br />
<br />
');
        $this->executeCommand(
            'connector',
            'test',
            'champs1',
            '{% if champs2 == "" %',
            'yes'
        );
    }

    public function testCommandUnknownScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope `unknown` is invalid. It needs to be in (module, connector)');
        $this->executeCommand('unknown', 'test', 'test', 'test', 'yes');
    }

    public function testCommandNoConfirmation(): void
    {
        $this->assertSame(
            1,
            $this->executeCommand('connector', 'test', 'test', 'test', 'no')
        );
    }
}
