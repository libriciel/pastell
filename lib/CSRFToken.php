<?php

use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;

class CSRFToken
{
    public const TOKEN_NAME =  'csrf_token';

    private $session;

    private $post_parameter;

    public function __construct()
    {
        $this->setPostParameter($_POST);
        if (isset($_SESSION)) {
            $this->setSession($_SESSION);
        }
    }

    public function setSession(array &$session)
    {
        $this->session = & $session;
    }

    public function setPostParameter(array $post_parameter)
    {
        $this->post_parameter = $post_parameter;
    }

    public function displayFormInput()
    {
        ?>
        <input type="hidden" name="<?php echo self::TOKEN_NAME ?>" value="<?php echo $this->getCSRFToken() ?>" />
        <?php
    }

    public function verifToken()
    {
        $this->verifParamToken();

        return true;
    }

    /**
     * @throws Exception
     */
    public function verifParamToken(?string $token = null): void
    {
        $to_test_token = $token ?? $this->post_parameter[self::TOKEN_NAME];
        if (
            empty($to_test_token) ||
            $to_test_token != $this->getCSRFToken()
        ) {
            throw new Exception("Votre session n'était plus valide. Le formulaire doit-être réinitialisé.");
        }
    }

    public function deleteToken()
    {
        if (isset($this->session[self::TOKEN_NAME])) {
            unset($this->session[self::TOKEN_NAME]);
        }
    }

    /**
     * @throws Exception
     */
    public function getCSRFToken()
    {
        if (empty($this->session[self::TOKEN_NAME])) {
            $this->session[self::TOKEN_NAME] = (new UriSafeTokenGenerator())->generateToken();
        }
        return $this->session[self::TOKEN_NAME];
    }
}
