<?php

declare(strict_types=1);

namespace Mailsec\Controller;

use DocumentSQL;
use DonneesFormulaireControler;
use DonneesFormulaireException;
use DonneesFormulaireFactory;
use Exception;
use FileUploader;
use Gabarit;
use Journal;
use Mailsec\Exception\InvalidKeyException;
use Mailsec\Exception\MissingPasswordException;
use Mailsec\Exception\NotEditableResponseException;
use Mailsec\Exception\UnableToExecuteActionException;
use Mailsec\Exception\UnavailableMailException;
use Mailsec\MailsecManager;
use ManifestFactory;
use NotFoundException;
use ObjectInstancier;
use ObjectInstancierFactory;
use PastellTimer;
use Recuperateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use UnrecoverableException;

final class RecipientController extends AbstractController
{
    private ObjectInstancier $objectInstancier;
    private Gabarit $gabarit;
    private ManifestFactory $manifestFactory;
    private PastellTimer $pastellTimer;
    private MailsecManager $mailsecManager;

    public function __construct()
    {
        $this->objectInstancier = ObjectInstancierFactory::getObjetInstancier();
        $this->mailsecManager = new MailsecManager($this->objectInstancier);
        $this->gabarit = $this->objectInstancier->getInstance(Gabarit::class);
        $this->manifestFactory = $this->objectInstancier->getInstance(ManifestFactory::class);
        $this->pastellTimer = $this->objectInstancier->getInstance(PastellTimer::class);
    }

    #[Route('/mail/invalid', name: 'mailsec_recipient_invalid', methods: ['GET'])]
    public function invalid(): Response
    {
        return $this->render('websec/invalid.html.twig', [
            'page_title' => 'Mail sécurisé invalide',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
        ]);
    }

    #[Route('/mail/unavailable', name: 'mailsec_recipient_unavailable', methods: ['GET'])]
    public function unavailable(): Response
    {
        return $this->render('websec/unavailable.html.twig', [
            'page_title' => 'Mail sécurisé indisponible',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
        ]);
    }

    /**
     * @throws MissingPasswordException
     * @throws InvalidKeyException
     * @throws NotFoundException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}', name: 'mailsec_recipient_index', methods: ['GET'])]
    public function index(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);

        return $this->render('websec/index.html.twig', [
            'page_title' => $mailSecInfo->denomination_entite . ' - Mail sécurisé',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
            'reponse_url' => $this->generateUrl('mailsec_recipient_reply', ['key' => $key]),
            'recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key]
            ),
            'reponse_recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key, 'fichier_reponse' => true]
            ),
            'download_all_link' => $this->generateUrl(
                'mailsec_recipient_downloadFiles',
                ['key' => $key]
            ),
            'inject' => [
                'id_e' => $mailSecInfo->id_e,
                'id_d' => $mailSecInfo->id_d,
                'action' => '',
                'id_ce' => false,
                'key' => $key,
            ],
            'mailSecInfo' => $mailSecInfo,
            'donneesFormulaire' => $mailSecInfo->donneesFormulaire,
            'fieldDataList' => $mailSecInfo->fieldDataList,
        ]);
    }

    /**
     * @throws MissingPasswordException
     * @throws InvalidKeyException
     * @throws NotFoundException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/captcha', name: 'mailsec_recipient_captcha', methods: ['GET'])]
    public function captcha(string $key, Request $request): Response
    {
        return $this->render('websec/captcha.html.twig', [
            'page_title' => 'Accéder au mail sécurisé',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
            'mail_url' => $this->generateUrl('mailsec_recipient_index', ['key' => $key]),
        ]);
    }

    /**
     * @throws MissingPasswordException
     * @throws NotFoundException
     * @throws InvalidKeyException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/password', name: 'mailsec_recipient_password', methods: ['GET', 'POST'])]
    public function password(string $key, Request $request): Response
    {
        if ($request->isMethod(Request::METHOD_POST)) {
            $password = $request->request->get('password');
            if ($this->mailsecManager->verifyPassword($key, $password)) {
                $ip = $request->getClientIp();
                $request->getSession()->set("consult_ok_{$key}_{$ip}", true);
                return $this->redirectToRoute('mailsec_recipient_index', ['key' => $key]);
            }
            $this->addFlash('danger', 'Le mot de passe est incorrect');
        }
        return $this->render('websec/password.html.twig', [
            'page_title' => 'Mot de passe Mail sécurisé',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
            'key' => $key,
        ]);
    }

    /**
     * @throws NotEditableResponseException
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws InvalidKeyException
     * @throws MissingPasswordException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/reply', name: 'mailsec_recipient_reply', methods: ['GET', 'POST'])]
    public function reply(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);
        $this->mailsecManager->checkResponseCanBeEdited($mailSecInfo);

        $mailSecInfo = $this->mailsecManager->createDocumentResponse($mailSecInfo);
        if ($request->isMethod(Request::METHOD_POST)) {
            $fileUploader = new FileUploader();
            $mailSecInfo->donneesFormulaireReponse->saveTab(
                new Recuperateur($request->request->all()),
                $fileUploader,
                0
            );
            $this->objectInstancier->getInstance(DocumentSQL::class)->setTitre(
                $mailSecInfo->id_d_reponse,
                $mailSecInfo->donneesFormulaireReponse->getTitre()
            );
            if ($request->request->get('ajouter') === 'ajouter') {
                return $this->redirectToRoute('mailsec_recipient_reply', ['key' => $key]);
            }
            if (!$mailSecInfo->donneesFormulaireReponse->isValidable()) {
                $this->addFlash('danger', $mailSecInfo->donneesFormulaireReponse->getLastError());
                return $this->redirectToRoute('mailsec_recipient_reply', ['key' => $key]);
            }

            return $this->redirectToRoute('mailsec_recipient_validate', ['key' => $key]);
        }

        return $this->render('websec/reply.html.twig', [
            'page_title' => $mailSecInfo->denomination_entite . ' - Réponse à un mail sécurisé',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
            'reponse_url' => $this->generateUrl('mailsec_recipient_reply', ['key' => $key]),
            'suppression_fichier_url' => $this->generateUrl(
                'mailsec_recipient_deleteFile',
                ['key' => $key]
            ),
            'recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key]
            ),
            'reponse_recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key, 'fichier_reponse' => true]
            ),
            'download_all_link' => $this->generateUrl(
                'mailsec_recipient_downloadFiles',
                ['key' => $key]
            ),
            'inject' => [
                'id_e' => $mailSecInfo->id_e,
                'id_d' => $mailSecInfo->id_d_reponse,
                'action' => '',
                'id_ce' => false,
                'key' => $key,
            ],
            'mailSecInfo' => $mailSecInfo,
            'donneesFormulaire' => $mailSecInfo->donneesFormulaire,
            'fieldDataList' => $mailSecInfo->fieldDataList,
        ]);
    }

    /**
     * @throws NotEditableResponseException
     * @throws TransportExceptionInterface
     * @throws InvalidKeyException
     * @throws NotFoundException
     * @throws MissingPasswordException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/validate', name: 'mailsec_recipient_validate', methods: ['GET', 'POST'])]
    public function validate(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);
        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                $this->mailsecManager->validateResponse($mailSecInfo);
            } catch (UnableToExecuteActionException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('mailsec_recipient_reply', ['key' => $key]);
            }
            $this->addFlash('info', 'Votre réponse a été envoyée');
            return $this->redirectToRoute('mailsec_recipient_index', ['key' => $key]);
        }

        return $this->render('websec/validate.html.twig', [
            'page_title' => $mailSecInfo->denomination_entite . ' - Mail sécurisé - Validation de la réponse',
            'gabarit' => $this->gabarit,
            'manifest_info' => $this->manifestFactory->getPastellManifest(),
            'timer' => $this->pastellTimer,
            'reponse_url' => $this->generateUrl('mailsec_recipient_reply', ['key' => $key]),
            'validation_url' => $this->generateUrl('mailsec_recipient_validate', ['key' => $key]),
            'suppression_fichier_url' => $this->generateUrl(
                'mailsec_recipient_deleteFile',
                ['key' => $key]
            ),
            'recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key]
            ),
            'reponse_recuperation_fichier_url' => $this->generateUrl(
                'mailsec_recipient_downloadFile',
                ['key' => $key, 'fichier_reponse' => true]
            ),
            'download_all_link' => $this->generateUrl(
                'mailsec_recipient_downloadFiles',
                ['key' => $key]
            ),
            'inject' => [
                'id_e' => $mailSecInfo->id_e,
                'id_d' => $mailSecInfo->id_d,
                'action' => '',
                'id_ce' => false,
                'key' => $key,
            ],
            'mailSecInfo' => $mailSecInfo,
            'donneesFormulaire' => $mailSecInfo->donneesFormulaire,
            'fieldDataList' => $mailSecInfo->fieldDataList,
        ]);
    }


    /**
     * @throws MissingPasswordException
     * @throws InvalidKeyException
     * @throws UnavailableMailException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Route('/mail/{key}/chunkUpload', name: 'mailsec_recipient_chunkUpload', methods: ['GET', 'POST'])]
    public function chunkUpload(string $key, Request $request): Response
    {
        $this->mailsecManager->getMailsecInfo($key, $request);

        $this->objectInstancier->getInstance(DonneesFormulaireControler::class)->chunkUploadAction();
        return $this->redirectToRoute('mailsec_recipient_reply', ['key' => $key]);
    }

    /**
     * @throws NotEditableResponseException
     * @throws NotFoundException
     * @throws InvalidKeyException
     * @throws MissingPasswordException
     * @throws DonneesFormulaireException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/deleteFile', name: 'mailsec_recipient_deleteFile', methods: ['GET'])]
    public function deleteFile(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);
        $this->mailsecManager->checkResponseCanBeEdited($mailSecInfo);

        $field = $request->get('field');
        $num = (int)$request->get('num');

        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get(
            $mailSecInfo->id_d_reponse,
            $mailSecInfo->flux_reponse
        );
        $donneesFormulaire->removeFile($field, $num);
        return $this->redirectToRoute('mailsec_recipient_reply', ['key' => $key]);
    }

    /**
     * @throws MissingPasswordException
     * @throws InvalidKeyException
     * @throws NotFoundException
     * @throws UnavailableMailException
     */
    #[Route('/mail/{key}/downloadFile', name: 'mailsec_recipient_downloadFile', methods: ['GET'])]
    public function downloadFile(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);

        $field = $request->get('field');
        $num = (int)$request->get('num');
        $responseFile = $request->get('fichier_reponse');

        if ($responseFile) {
            $filePath = $mailSecInfo->donneesFormulaireReponse->getFilePath($field, $num);
            $fileName = $mailSecInfo->donneesFormulaireReponse->getFileName($field, $num);
            $mimeType = $mailSecInfo->donneesFormulaireReponse->getContentType($field, $num);
        } else {
            $filePath = $mailSecInfo->donneesFormulaire->getFilePath($field, $num);
            $fileName = $mailSecInfo->donneesFormulaire->getFileName($field, $num);
            $mimeType = $mailSecInfo->donneesFormulaire->getContentType($field, $num);
        }
        if (!file_exists($filePath)) {
            $this->addFlash('error', "Ce fichier n'existe pas");
            return $this->redirectToRoute('mailsec_recipient_index', ['key' => $key]);
        }

        $response = $this->file($filePath, $fileName);
        $response->headers->set('Content-Type', $mimeType);
        $this->objectInstancier->getInstance(Journal::class)->add(
            Journal::DOCUMENT_CONSULTATION,
            $mailSecInfo->id_e,
            $mailSecInfo->id_d,
            'Consulté',
            "{$mailSecInfo->email} a consulté le fichier $fileName"
        );
        return $response;
    }

    /**
     * @throws MissingPasswordException
     * @throws InvalidKeyException
     * @throws UnavailableMailException
     * @throws NotFoundException
     * @throws Exception
     */
    #[Route('/mail/{key}/downloadFiles', name: 'mailsec_recipient_downloadFiles', methods: ['GET'])]
    public function downloadFiles(string $key, Request $request): Response
    {
        $mailSecInfo = $this->mailsecManager->getMailsecInfo($key, $request);

        $field = $request->get('field');
        $responseFile = (bool)$request->get('fichier_reponse');

        $documentId = $responseFile ? $mailSecInfo->id_d_reponse : $mailSecInfo->id_d;

        \ob_start();
        $this->objectInstancier->getInstance(DonneesFormulaireControler::class)->downloadAll(
            $mailSecInfo->id_e,
            $documentId,
            false,
            $field
        );
        $content = (string)\ob_get_clean();

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/zip');

        return $response;
    }
}
