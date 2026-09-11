<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string[] $recovery_codes
 */

?>

<div class="box">
    <h2>Vos codes de récupération</h2>

    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>&nbsp;
        Conservez ces codes en lieu sûr. Ils ne seront <strong>plus jamais affichés</strong>.
        Chaque code ne peut être utilisé qu'une seule fois pour vous connecter si vous perdez
        votre application d'authentification.
    </div>

    <ul id="mfa-recovery-codes" class="list-unstyled">
        <?php foreach ($recovery_codes as $code) : ?>
            <li><strong><?php hecho($code); ?></strong></li>
        <?php endforeach; ?>
    </ul>

    <button type="button" id="mfa-copy-codes" class="btn btn-primary">
        <i class="fas fa-copy"></i>&nbsp;Copier les codes
    </button>
    <button type="button" id="mfa-download-codes" class="btn btn-primary">
        <i class="fas fa-download"></i>&nbsp;Télécharger
    </button>

    <a href="Utilisateur/moi" class="btn btn-outline-primary">
        <i class="fas fa-check"></i>&nbsp;J'ai enregistré mes codes
    </a>

    <script type="text/javascript">
        $(function () {
            var codes = <?php echo json_encode($recovery_codes, JSON_THROW_ON_ERROR); ?>;
            var text = codes.join('\n');

            $('#mfa-copy-codes').on('click', function () {
                var $button = $(this);
                var done = function () {
                    $button.html('<i class="fas fa-check"></i>&nbsp;Copiés');
                    setTimeout(function () {
                        $button.html('<i class="fas fa-copy"></i>&nbsp;Copier les codes');
                    }, 2000);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    var $temp = $('<textarea>').val(text).appendTo('body');
                    $temp.trigger('select');
                    document.execCommand('copy');
                    $temp.remove();
                    done();
                }
            });

            $('#mfa-download-codes').on('click', function () {
                var blob = new Blob([text + '\n'], {type: 'text/plain'});
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'pastell-codes-recuperation.txt';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        });
    </script>
</div>
