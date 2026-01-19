<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

abstract class SEDAConnecteur extends Connecteur
{
    public function __construct(
        private readonly int $archiveCreationTimeout,
    ) {
    }

    /**
     * Crée le bordereau en fonction des informations provenant du flux
     */
    abstract public function getBordereau(FluxData $fluxData): string;

    /**
     * Permet de valider un bordereau SEDA en fonction des schéma du connecteur
     */
    abstract public function validateBordereau(string $bordereau): bool;

    /**
     * Permet de récupérer les erreurs provenant de la validation du bordereau SEDA
     * @return LibXMLError[]
     */
    abstract public function getLastValidationError(): array;

    /**
     *
     * Génère l'archive en fonction des données du flux sur archive_path
     */
    abstract public function generateArchive(FluxData $fluxData, string $archive_path): void;

    public function generateArchiveThrow(FluxData $fluxData, string $archive_path, string $tmp_folder): void
    {
        if (\count($fluxData->getFilelist()) === 0) {
            throw new \RuntimeException('Aucun fichier à archiver');
        }
        foreach ($fluxData->getFilelist() as $file_id) {
            $filename = $file_id['filename'];
            $filepath = $file_id['filepath'];

            if (!$filepath) {
                break;
            }
            $dirname = \dirname($tmp_folder . '/' . $filename);
            $filesystem = new Filesystem();
            if (!$filesystem->exists($dirname)) {
                $filesystem->mkdir($dirname);
            }
            \copy($filepath, "$tmp_folder/$filename");
        }

        $process = Process::fromShellCommandline(
            \sprintf(
                'bash -c \'cd %s && shopt -s nullglob dotglob && tar -czvf %s -- * 2>&1\'',
                $tmp_folder,
                $archive_path,
            )
        );
        dd($this->archiveCreationTimeout);
        $process->setTimeout($this->archiveCreationTimeout);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $process->getOutput();
            $exitCode = $process->getExitCode();
            throw new \RuntimeException(
                "Impossible de créer le fichier d'archive $archive_path - status : $exitCode - output: $output"
            );
        }
    }
}
