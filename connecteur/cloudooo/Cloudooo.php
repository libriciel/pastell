<?php

declare(strict_types=1);

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;

/**
 * @deprecated 5.0.0
 */
class Cloudooo extends ConvertisseurPDF
{
    private string $cloudooo_hostname;
    private string $cloudooo_port;

    public function __construct(
        private TmpFolder $tmpFolder,
    ) {
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->cloudooo_hostname = $donneesFormulaire->get('cloudooo_hostname');
        $this->cloudooo_port = $donneesFormulaire->get('cloudooo_port');
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function convertToPDF(string $source): string
    {
        if (!file_exists($source) || !is_readable($source)) {
            throw new Exception("Impossible de lire le fichier $source ");
        }

        $info = pathinfo($source);
        $new_filename = $info['filename'] . '.pdf';
        $new_filepath = dirname($source) . "/" . $new_filename;

        $dataaConvertir = file_get_contents($source);

        $dataExtention = pathinfo($source, PATHINFO_EXTENSION);
        $dataSortieExtention = 'pdf';

        $url = sprintf("http://%s:%s", $this->cloudooo_hostname, $this->cloudooo_port);
        $client = new Client($url);

        $result = $client->send(
            new Request(
                'convertFile',
                [
                    new Value(base64_encode($dataaConvertir)),
                    new Value($dataExtention),
                    new Value($dataSortieExtention),
                    new Value(false),
                    new Value(true),
                ]
            )
        );

        if ($result->faultCode()) {
            throw new UnrecoverableException('Exception #' . $result->faultCode() . ' : ' . $result->faultString());
        }

        file_put_contents($new_filepath, base64_decode($result->value()->scalarval()));


        if (!file_exists($new_filepath)) {
            throw new Exception("Le fichier « $new_filepath » n'a pas pu être créé");
        }

        return $new_filepath;
    }


    /**
     * @throws UnrecoverableException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function convertField(
        DonneesFormulaire $donneesFormulaire,
        string $input_field_name,
        string $output_field_name,
    ): void {
        $filename = $donneesFormulaire->getFileName($input_field_name);
        $file_path = $donneesFormulaire->getFilePath($input_field_name);

        $tmp_folder = $this->tmpFolder->create();

        try {
            $tmp_file_source = $tmp_folder . "/" . $filename;
            copy($file_path, $tmp_file_source);
            $new_filepath = $this->convertToPDF($tmp_file_source);
            $new_filename = basename($new_filepath);
            $donneesFormulaire->addFileFromCopy($output_field_name, $new_filename, $new_filepath, 0);
        } finally {
            $this->tmpFolder->delete($tmp_folder);
        }
    }
}
