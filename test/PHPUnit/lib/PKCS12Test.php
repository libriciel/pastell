<?php

class PKCS12Test extends PHPUnit\Framework\TestCase
{
    /**
     * @var PKCS12
     */
    private $pkcs12;
    private $p12_file_path;
    private $p12_password;
    private $certificate_name;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pkcs12 = new PKCS12();
        $this->p12_file_path = __DIR__ . "/fixtures/certificat.p12";
        $this->p12_password = "certificat";
        $this->certificate_name = "/C=FR/ST=HERAULT/L=MONTPELLIER/O=LIBRICIEL/OU=CERTIFICAT_AUTO_SIGNE/CN=localhost/emailAddress=test@localhost";
    }

    public function testGetAll()
    {
        $all = $this->pkcs12->getAll($this->p12_file_path, $this->p12_password);
        $info = openssl_x509_parse($all['cert']);
        $this->assertEquals($this->certificate_name, $info['name']);
        openssl_x509_check_private_key($all['cert'], $all['pkey']);
    }

    public function testNotExists()
    {
        $this->assertFalse($this->pkcs12->getAll('foo', 'bar'));
    }

    public function testBasPassword()
    {
        $this->assertFalse($this->pkcs12->getAll($this->p12_file_path, "bad password"));
    }

    public function testUnencryptedKey()
    {
        $result = $this->pkcs12->getUnencryptedKey($this->p12_file_path, $this->p12_password);
        $this->assertMatchesRegularExpression("#-----BEGIN PRIVATE KEY-----#", $result);
    }

    /**
     * FIXME: Decide on legacy OpenSSL provider support strategy
     *
     *  Options:
     *  1. Remove legacy provider support -> delete this test
     *  2. Maintain legacy provider support -> fix and reactivate this test
     *
     */
//    public function testWithLegacyOpensslProvider()
//    {
//        $all = $this->pkcs12->getAll(__DIR__ . "/fixtures/demou.p12", "demou");
//        $this->assertNotFalse($all);
//        $info = openssl_x509_parse($all['cert']);
//        $this->assertSame("7e899712", $info['hash']);
//    }
}
