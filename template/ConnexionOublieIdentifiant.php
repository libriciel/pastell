<?php

/**
 * @var Gabarit $this
 * @var string $login_page_configuration
 * @var DonneesFormulaire $config
 */
?>

<?php if ($this->getLastError()->getLastError() || $this->getLastMessage()->getLastMessage()) : ?>
    <ls-lib-forgot-password-success
            visual-configuration='<?php hecho($login_page_configuration); ?>'
            logo="img/commun/pastell-color-grey.svg"
    >
    </ls-lib-forgot-password-success>

    <script>
        document.getElementsByTagName("ls-lib-forgot-password-success")[0].addEventListener('backToLogin', function () {
            window.location = "<?php $this->url("Connexion/connexion") ?>";
        });
    </script>

<?php else : ?>
    <ls-lib-forgot-password
            visual-configuration='<?php hecho($login_page_configuration); ?>'
            logo="img/commun/pastell-color-grey.svg"
            form-action="<?php $this->url('Connexion/doOublieIdentifiant'); ?>"
            one-field-forgot=true
            usernameormail-input-name="login"
    >
        <?php $this->displayCSRFInput() ?>

        <?php if ($config && $config->get('procedure_recup')) : ?>
            <style>
                #ls-forgot-form .usernameormail,
                #ls-forgot-form > .alert,
                #ls-forgot-form .btn-primary {
                    display: none;
                }
            </style>
            <div class="forgot-form-addons">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $this->getHTMLPurifier()->purify(nl2br($config->get('message'))); ?>
                </div>
            </div>
        <?php endif; ?>
    </ls-lib-forgot-password>

    <script>
        document.getElementsByTagName("ls-lib-forgot-password")[0].addEventListener('backToLogin', function () {
            window.location = "<?php $this->url("Connexion/connexion") ?>";
        });
    </script>

<?php endif; ?>

