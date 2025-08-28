<?php

class ConnecteurFrequenceTest extends PastellTestCase
{
    public function testConstruct()
    {
        $connecteurFrequence = new ConnecteurFrequence(['type_connecteur' => 'toto','id_cf' => 12]);
        $this->assertEquals('toto', $connecteurFrequence->type_connecteur);
    }

    public function testGetArray()
    {
        $connecteurFrequence = new ConnecteurFrequence(['type_connecteur' => 'toto','id_cf' => 12]);
        $this->assertEquals('toto', $connecteurFrequence->getArray()['type_connecteur']);
    }

    public function testGetgetAttributeName()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $result = $connecteurFrequence->getAttributeName();
        $this->assertCount(9, $result);
        $this->assertEquals('id_verrou', $result[8]);
    }

    public function testGetConnecteurSelectorAll()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $this->assertEquals("Tous les connecteurs", $connecteurFrequence->getConnecteurSelector());
    }

    public function testGetConnecteurSelectorGlobal()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_GLOBAL;
        $this->assertEquals("(Global) Tous les connecteurs", $connecteurFrequence->getConnecteurSelector());
    }

    public function testGetConnecteurSelectorEntite()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $this->assertEquals("(Entité) Tous les connecteurs", $connecteurFrequence->getConnecteurSelector());
    }

    public function testGetConnecteurSelectorFamille()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->id_ce = 1;
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = "signature";
        $this->assertEquals("(Entité) signature", $connecteurFrequence->getConnecteurSelector());
    }

    public function testGetConnecteurSelectorConnecteur()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->id_ce = 1;
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = "signature";
        $connecteurFrequence->id_connecteur = "i-parapheur";
        $this->assertEquals("(Entité) signature:i-parapheur", $connecteurFrequence->getConnecteurSelector());
    }

    public function testGetActionSelectorAll()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $this->assertEquals("Toutes les actions", $connecteurFrequence->getActionSelector());
    }

    public function testGetActionSelectorType()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_CONNECTEUR;
        $this->assertEquals("(Connecteur) toutes les actions", $connecteurFrequence->getActionSelector());
    }

    public function testGetActionSelectorAction()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_CONNECTEUR;
        $connecteurFrequence->action = 'recup-type';
        $this->assertEquals("(Connecteur) recup-type", $connecteurFrequence->getActionSelector());
    }

    public function testGetActionSelectorDocumentAll()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $this->assertEquals("(Document) Tous les types de dossiers", $connecteurFrequence->getActionSelector());
    }

    public function testGetActionSelectorDocument()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'actes-generique';
        $this->assertEquals("(Document) actes-generique: toutes les actions", $connecteurFrequence->getActionSelector());
    }

    public function testGetActionSelectorDocumentAction()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'actes-generique';
        $connecteurFrequence->action = 'verif-signature';
        $this->assertEquals("(Document) actes-generique: verif-signature", $connecteurFrequence->getActionSelector());
    }

    /**
     * @throws Exception
     */
    public function testGetNextTryEmpty(): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        static::assertSame('', $connecteurFrequence->getNextTry(42));
    }

    private function assertFrequency(int $frequencyInMinutes, string $date): void
    {
        $expectedTime = strtotime("+$frequencyInMinutes minute");
        $actualTime = strtotime($date);
        $diff = $expectedTime - $actualTime;
        static::assertLessThanOrEqual(
            1,
            $diff,
            "Failed that $date is " . date('Y-m-d H:i:s', $expectedTime)
        );
    }

    /**
     * @throws Exception
     */
    public function testGetNextTry(): void
    {
        $frequence_in_minute = 5;
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = (string)$frequence_in_minute;
        $date = $connecteurFrequence->getNextTry(1);
        $this->assertFrequency($frequence_in_minute, $date);
    }

    /**
     * @throws Exception
     */
    public function testGetNextTryFrequence(): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1X5\n60";
        $date = $connecteurFrequence->getNextTry(1);
        $this->assertFrequency(1, $date);
    }

    /**
     * @throws Exception
     */
    public function testGetNextTryFrequenceLoin(): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1X5\n60";
        $date = $connecteurFrequence->getNextTry(10);
        $this->assertFrequency(60, $date);
    }

    /**
     * @throws Exception
     */
    public function testGetNextTryFrequenceSpace(): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1 X 5\n60 X 10";
        $date = $connecteurFrequence->getNextTry(10);
        $this->assertFrequency(60, $date);
    }

    /**
     * @dataProvider frequenceProvider
     * @throws Exception
     */
    public function testGetNextTryFrequencePlusLoin($minute_expected, $nb_try): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1X5\n60X10\n1X25\n42";
        $this->assertFrequency($minute_expected, $connecteurFrequence->getNextTry($nb_try));
    }

    public function frequenceProvider(): \Generator
    {
        yield [1, 0];
        yield [1, 1];
        yield [1, 4];
        yield [60, 5];
        yield [60, 14];
        yield [1, 15];
        yield [1, 49];
        yield [42, 50];
        yield [42, 500];
    }

    public function expressionsProvider(): iterable
    {
        yield 'every 10 minutes' => ['10', '2012-06-27 18:23:46', '2012-06-27 18:33:46'];
        yield 'at 2:40' => ['(40 2 * * *)', '2017-04-13 11:48:45', '2017-04-14 02:40:00'];
        yield 'every 10 seconds' => ['10s', '2022-06-14 00:00:00', '2022-06-14 00:00:10'];
        yield '43 times every second' => ['1s X 43', '2022-06-14 00:00:00', '2022-06-14 00:00:01'];
    }

    /**
     * @dataProvider expressionsProvider
     * @throws Exception
     */
    public function testNextTry(string $expression, string $relativeDate, string $expectedNextTry): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = $expression;
        $next = $connecteurFrequence->getNextTry(42, $relativeDate);
        $this->assertSame($expectedNextTry, $next);
    }

    /**
     * @throws Exception
     */
    public function testLastExpression()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Trop d'essai sur le connecteur");
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1 X 10";
        $connecteurFrequence->getNextTry(11);
    }

    public function testGetExpressionAsString(): void
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->expression = "1 X 10\n10s X 10\n(* * * * *) X 1\n60 X 1";
        $expr = $connecteurFrequence->getExpressionAsString();
        $this->assertSame(
            <<<EOT
Toutes les minutes (10 fois)
Toutes les 10 secondes (10 fois)
A (* * * * *) (1 fois)
Toutes les 60 minutes (1 fois)
Suspendre le travail
EOT
            ,
            $expr
        );
    }

    public function testGetExpressionAsStringEmpty()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $expr = $connecteurFrequence->getExpressionAsString();
        $this->assertEquals("\n", $expr);
    }
}
