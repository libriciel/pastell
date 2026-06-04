<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class GlaneurLancerGlanage extends ActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var GlaneurConnecteur $connecteur */
        $connecteur = $this->getMyConnecteur();

        try {
            $result = $connecteur->glaner();
            $this->setLastMessage(implode("<br/>", $connecteur->getLastMessage()));
        } catch (UnrecoverableException $e) {
            $jobQueue = $this->objectInstancier->getInstance(JobQueueSQL::class);

            $id_job  = $jobQueue->getJobIdForConnecteur($this->id_ce, 'go');
            if ($id_job) {
                $jobQueue->lock($id_job, Job::ERROR_ACTION);
            }
            $message = $e->getMessage();
            $this->setLastMessage($message);

            $url = sprintf('%s/Connecteur/edition?id_ce=%d', $this->getSiteBase(), $this->id_ce);

            #TODO revoir la gestion des erreurs des connecteurs afin de ne pas envoyer de mail à ce moment-là
            $configurationSql = $this->objectInstancier->getInstance(ConfigurationSQL::class);
            $admin_email = $configurationSql->getAdminEmails();
            $plateforme_mail = $this->objectInstancier->getInstance('plateforme_mail');
            $libelle_plateforme_mail = $configurationSql->getLibellePlateformeMail();
            $templatedEmail = new TemplatedEmail()
                ->from(new Address($plateforme_mail, $libelle_plateforme_mail))
                ->to(...$admin_email)
                ->subject("[Pastell] Le traitement d'un glaneur est passé à 'NON'")
                ->htmlTemplate('glaneur_lancer_glanage.html.twig')
                ->context(['url' => $url, 'message' => $message]);
            $this->objectInstancier->getInstance(Mailer::class)->send($templatedEmail);
            return false;
        } catch (Exception $e) {
            $this->setLastMessage("Erreur lors de l'importation : " . $e->getMessage() . "<br />\n");
            return false;
        }

        return $result;
    }
}
