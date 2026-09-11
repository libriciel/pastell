<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $secret
 * @var string $qr_code_svg
 */

?>

<a href='Mfa/cancelEnrolement' class="btn btn-link"><i class="fas fa-arrow-left"></i>&nbsp;Espace utilisateur</a>

<div class="box">
    <div id="mfa-qr-step">
        <p>Scannez le QR code avec votre application d'authentification
            (Google Authenticator, Aegis, FreeOTP, etc.).</p>

        <div class="text-start my-3">
            <?php echo $qr_code_svg; ?>
        </div>

        <p>
            Impossible de scanner ? Vous pouvez utiliser la
            <button type="button" id="mfa-show-key" class="btn btn-link p-0 align-baseline text-primary text-decoration-underline">clé de configuration</button>
            pour configurer manuellement votre application d'authentification.
        </p>
    </div>

    <div id="mfa-key-step" class="d-none">
        <p>Saisissez cette clé de configuration dans votre application d'authentification.</p>

        <p class="d-flex align-items-center gap-2">
            <strong id="mfa-secret"><?php hecho($secret); ?></strong>
            <button type="button" id="mfa-copy-key" class="btn btn-primary"
                    data-secret="<?php hecho($secret); ?>">
                <i class="fas fa-copy"></i>&nbsp;Copier la clé
            </button>
        </p>

        <p>
            <button type="button" id="mfa-show-qr" class="btn btn-link p-0 align-baseline text-primary text-decoration-underline">
                Afficher le QR code
            </button>
        </p>
    </div>

    <script type="text/javascript">
        $(function () {
            $('#mfa-show-key').on('click', function () {
                $('#mfa-qr-step').addClass('d-none');
                $('#mfa-key-step').removeClass('d-none');
            });
            $('#mfa-show-qr').on('click', function () {
                $('#mfa-key-step').addClass('d-none');
                $('#mfa-qr-step').removeClass('d-none');
            });
            $('#mfa-copy-key').on('click', function () {
                var $button = $(this);
                var secret = $button.data('secret');
                var done = function () {
                    $button.html('<i class="fas fa-check"></i>&nbsp;Copié');
                    setTimeout(function () {
                        $button.html('<i class="fas fa-copy"></i>&nbsp;Copier la clé');
                    }, 2000);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(secret).then(done);
                } else {
                    var $temp = $('<textarea>').val(secret).appendTo('body').select();
                    document.execCommand('copy');
                    $temp.remove();
                    done();
                }
            });
        });
    </script>

    <form action='Mfa/doEnrolement' method='post'>
        <?php $this->displayCSRFInput(); ?>

        <div class="form-group row">
            <label for="code" class="col-sm-3 col-form-label">Saisissez le code de l'application<span class='obl'>*</span></label>
            <div class="col-md-4">
                <input name="code" id="code" class="form-control" type="text"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
            </div>
        </div>

        <a class='btn btn-outline-primary' href='Mfa/cancelEnrolement'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Activer
        </button>
    </form>
</div>
