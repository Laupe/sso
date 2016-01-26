<?php
namespace ModuleSSO\ClientLoginMethod\ClientThirdPartyLogin;

use ModuleSSO\ClientLoginMethod\ClientThirdPartyLogin;
use ModuleSSO\LoginMethod\ThirdPartyLogin\FacebookLogin;

class ClientFacebookLogin extends ClientThirdPartyLogin
{
    public function showLogin($continue = '')
    {
        $src = CFG_SSO_ENDPOINT_URL . '?' . \ModuleSSO::CONTINUE_KEY . '=' . CFG_DOMAIN_URL . '&' . \ModuleSSO::METHOD_KEY . '=' . FacebookLogin::METHOD_NUMBER;
        return '<a href="' . $src . '"><img src="' . CFG_SSO_URL . '/img/fbLogin.png"/></a>';
    }
}