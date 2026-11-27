<?php

use Pastell\Service\Document\DocumentReindexService;

/**
 * @var ObjectInstancier $objectInstancier
 */

require_once __DIR__ . '/../init.php';

if (count($argv) < 3) {
    echo "{$argv[0]} : Permet de réindexer un ensemble de document d'un certain type pour un champ donnée\n";
    echo "Usage : {$argv[0]} type_de_document champ_a_reindexer [offset] [limit]\n";
    exit;
}

$document_type = get_argv(1);
$champs_reindex = get_argv(2);

$offset = get_argv(3) ?: 0;
$limit = get_argv(4) ?: -1;

$documentReindexService = $objectInstancier->getInstance(DocumentReindexService::class);
try {
    $document_list = $documentReindexService->getDocumentsToReindex(
        $document_type,
        [$champs_reindex],
        (int) $offset,
        (int) $limit
    );
} catch (UnrecoverableException $e) {
    echo '[ERREUR] ' . $e->getMessage() . "\n";
    exit(1);
}

echo 'Nombre de documents : ' . count($document_list) . "\n";
foreach ($document_list as $i => $document_info) {
    echo "Réindexation du document {$document_info['titre']} ({$document_info['id_d']})\n";
    $documentReindexService->reindexDocument($document_info['id_d'], [$champs_reindex]);
    if (($i + 1) % 100 === 0) {
        gc_collect_cycles();
    }
}
