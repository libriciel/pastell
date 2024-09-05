<?php

declare(strict_types=1);

use Pastell\Configuration\ElementType;

class FieldDataTest extends PHPUnit\Framework\TestCase
{
    public function testSetValue(): void
    {
        $field = new Field('test', ['depend' => true, 'type' => 'select', 'value' => [1 => 'pim', 'pam', 'poum']]);
        $fieldData = new FieldData($field, ['test' => 0]);
        static::assertEquals(['test: non défini'], $fieldData->getValue());
    }

    public function testNoDefaultForDate(): void
    {
        $field = new Field('test', ['type' => 'date']);
        $fieldData = new FieldData($field, '');
        static::assertEquals([], $fieldData->getValue());
    }

    public function getUrlProvider(): \Generator
    {
        yield ['http://url.tld', 'http://url.tld?field=test&num=0'];
        yield ['http://url.tld?param=1', 'http://url.tld?param=1&field=test&num=0'];
    }

    /**
     * @dataProvider getUrlProvider
     */
    public function testGetUrl(string $url, string $expected): void
    {
        $field = new Field('test', ['type' => 'file']);
        $fieldData = new FieldData($field, '');

        static::assertSame($expected, $fieldData->getURL($url, 0, 1));
    }

    public function testisMail(): void
    {
        $field = new Field('to', ['type' => 'mail-list']);
        $fieldData = new FieldData($field, '"a@a.aa" <a@a.aa>');

        static::assertTrue($fieldData->isMailList());
    }

    public function fieldDataProvider(): array
    {
        $fieldNames = [ElementType::TEXT, ElementType::TEXTAREA, ElementType::SELECT];
        $values = [
            ['0', true],
            ['1', true],
            ['true', true],
            ['false', true],
            ['on', true],
            ['off', true],
            ['yes', true],
            ['no', true],
            ['y', true],
            ['n', true],
            ['+', true],
            ['-', true],
            [' ', true],
            ["\n", true],
            ["\t", true],
            ["\r", true],
            ["\0", true],
            ["\u{1F600}", true],
            ['', false],
        ];

        $data = [];
        foreach ($fieldNames as $fieldName) {
            foreach ($values as $value) {
                $data["$fieldName->value : '$value[0]'"] = array_merge([$fieldName], $value);
            }
        }
        return $data;
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testSpecialStringValueForField($fieldType, $fieldValue, $expected): void
    {
        $field = new Field('test', ['type' => $fieldType, 'requis' => true, 'value' => $fieldValue]);
        $fieldData = new FieldData($field, $fieldValue);
        static::assertSame($expected, $fieldData->isValide());
    }
}
