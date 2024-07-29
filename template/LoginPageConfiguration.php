<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $login_page_configuration
 */

?>
<script type="module" src="node_modules/@libriciel/ls-auth-native/ls-auth.js"></script>

<ls-login-config></ls-login-config>

<form
    id='login-page-configurator'
    action='<?php $this->url('System/doLoginPageConfiguration'); ?>'
    method='POST'
>
    <?php $this->getCSRFToken()->displayFormInput() ?>

    <input  id='login-page-configurator-input'  type="hidden" name='login_page_configuration' value=''/>
    <button id='login-page-configurator-submit' type="submit" style="display: none"></button>
</form>


<script>
    const loginConfigElement = document.getElementsByTagName("ls-login-config")[0];
    loginConfigElement.configuration = <?php echo($login_page_configuration ?: '{}'); ?>;
    loginConfigElement.addEventListener('save', config => {
        setTimeout(
            () => {
                document.getElementById('login-page-configurator-input').value = JSON.stringify(config.detail);
                document.getElementById('login-page-configurator').submit();
            });
    });
</script>
