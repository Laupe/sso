<?php
namespace ModuleSSO;

use ModuleSSO\Client\LoginHelper\Renderer\IRenderer;
use ModuleSSO\EndPoint\LoginMethod\HTTP as ELHTTP;
use ModuleSSO\Client\LoginHelper\HTTP;

use ModuleSSO\EndPoint\LoginMethod\Other as ELOther;
use ModuleSSO\Client\LoginHelper\Other;

use ModuleSSO\EndPoint\LoginMethod\ThirdParty as ELThirdParty;
use ModuleSSO\Client\LoginHelper\ThirdParty;

use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Parser;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class Client
 * @package ModuleSSO
 */
class Client extends \ModuleSSO
{
    /**
     * @var string $publicKey
     */
    private $publicKey = '';

    /**
     * @var \ModuleSSO\Client\LoginHelper $loginHelper
     */
    private $loginHelper = null;

    /**
     * @var Request $request
     */
    public $request = null;

    /**
     * @var IRenderer
     */
    public $renderer = null;

    /** @var array $MAP */
    private static $MAP = array(
        ELHTTP\NoScriptLogin::METHOD_NUMBER => '\ModuleSSO\Client\LoginHelper\HTTP\NoScriptHelper',
        ELHTTP\IframeLogin::METHOD_NUMBER => '\ModuleSSO\Client\LoginHelper\HTTP\IframeHelper',
        ELOther\CORSLogin::METHOD_NUMBER => '\ModuleSSO\Client\LoginHelper\Other\CORSHelper',
        ELThirdParty\FacebookLogin::METHOD_NUMBER => '\ModuleSSO\Client\LoginHelper\ThirdParty\FacebookHelper',
        ELThirdParty\GoogleLogin::METHOD_NUMBER => '\ModuleSSO\Client\LoginHelper\ThirdParty\GoogleHelper'
        
    );

    /**
     * Client constructor
     *
     * @param Request $request
     * @param IRenderer $renderer
     * @param string $pubKeyPath Path to public key
     */
    public function __construct(Request $request, IRenderer $renderer, $pubKeyPath = 'app/config/pk.pub')
    {
        $this->request = $request;
        $this->renderer = $renderer;
        $this->publicKey = file_get_contents($pubKeyPath);
    }

    /**
     * Method finds and return user from database based on ID provided in $_SESSION
     *
     * @uses $_SESSION
     * @uses \Database
     *
     * @return mixed If user is found, return array, otherwise null
     */
    public function getUser()
    {
        if(isset($_SESSION['uid'])) {
            $query = \Database::$pdo->prepare("SELECT * FROM users WHERE id = ?");
            $query->execute(array($_SESSION['uid']));
            return $query->fetch();
        } else {
            return null;
        }
    }

    /**
     * Returns requested URL if there is one, otherwise returns default CFG_DOMAIN_URL
     *
     * @return string
     */
    public function getContinueUrl()
    {
        $base = CFG_DOMAIN_URL;
        if($rqu = $this->request->server->get('REQUEST_URI')) {
            $result = parse_url($rqu);
            if(!empty($result['path'])) {
                $path = $result['path'];
                $base =  $base . $path;
            }
        }
        return $base;
    }

    /**
     * Sets login helper
     *
     * @param Client\LoginHelper $loginHelper
     */
    public function setLoginHelper(Client\LoginHelper $loginHelper)
    {
        $this->loginHelper = $loginHelper;
        $this->loginHelper->renderer = $this->renderer->getRenderer($this->loginHelper);
    }

    /**
     * Returns login helper
     *
     * @return Client\LoginHelper
     */
    public function getLoginHelper()
    {
        return $this->loginHelper;
    }

    /**
     * Sets $loginHelper according to parameter passed in $_GET
     * If there is no parameter, Client::$loginHelper is according to config file
     * Client::$loginHelper depends on capabilities of browser
     *
     * @link http://caniuse.com/#feat=cors
     *
     * @uses $_GET
     * @uses Client::$loginHelper
     * @uses ModuleSSO
     * @uses NoScriptLogin
     * @uses IframeLogin
     * @uses CORSLogin
     * @uses FacebookLogin
     * @uses GoogleLogin
     * @uses DirectLogin
     *
     */
    public function pickLoginHelper()
    {
        if($key = $this->request->query->get(\ModuleSSO::FORCED_METHOD_KEY)) {
            if(isset(self::$MAP[$key])) {
                $class = self::$MAP[$key];
                $this->loginHelper = new $class();
            } else {
                $this->loginHelper = new HTTP\NoScriptHelper();
            }
            $this->loginHelper->renderer = $this->renderer->getRenderer($this->loginHelper);
            return;
        }
        
        //config
        global $loginHelperPriorities;
        foreach ($loginHelperPriorities as $helper) {
            /** @var \ModuleSSO\Client\LoginHelper $loginHelper */
            $loginHelper = new $helper();
            if($loginHelper->isSupported()) {
                $this->loginHelper = $loginHelper;
                $this->loginHelper->renderer = $this->renderer->getRenderer($this->loginHelper);
                break;
            }
        } 
    }

    /**
     * Method for appending JavaScript scripts to HTML
     *
     * @uses LoginHelper::appendScripts()
     */
    public function appendScripts()
    {
        echo $this->loginHelper->appendScripts();
    }

    /**
     * Method for appending CSS styles to HTML
     *
     * @uses LoginHelper::appendStyles()
     */
    public function appendStyles()
    {
        echo $this->loginHelper->appendStyles();
    }

    /**
     * Shows login form HTML of current loginHelper
     * @return string
     *
     * @uses LoginHelper::showLogin()
     * @uses Client::getContinueUrl()
     */
    public function showLogin() {
        $this->loginHelper->showLogin($this->getContinueUrl());
    }

    /**
     * Starts lifecycle of Client
     *
     * @uses Client::setOnTokenRequest()
     * @uses Client::setOnLogoutRequest()
     */
    public function run()
    {
        $this->setOnTokenRequest();
        $this->setOnLogoutRequest();
    }

    /**
     * Waits for token given in $_GET, parses it and creates local context for user (logs user in)
     *
     * @uses $_GET
     * @uses ModuleSSO
     */
    public function setOnTokenRequest() {
        if($urlToken = $this->request->query->get(\ModuleSSO::TOKEN_KEY)) {
            try {
                $token = (new Parser())->parse((string) $urlToken);
                $signer = new Sha256();
                $pk = new Key($this->publicKey);

                //check if token is signed and not expired
                if($token->verify($signer, $pk) && $token->getClaim('exp') > time()) {
                    $query = \Database::$pdo->prepare("SELECT * FROM tokens WHERE value = '$urlToken' AND used = 0");
                    $query->execute();
                    $dbtoken = $query->fetch();                          
                    if($dbtoken) {
                        $query = \Database::$pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $query->execute(array($token->getClaim('uid')));
                        $user = $query->fetch();
                        if($user) {
                            $query = \Database::$pdo->prepare("UPDATE tokens SET used = 1 WHERE value = '$urlToken'");
                            $query->execute();
                            
                            $_SESSION['uid'] = $user['id'];
                            RedirectResponse::create($this->getContinueUrl())->send();
                        }
                    }
                } else {
                    Messages::insert('Login failed, please try again', 'warn');
                    RedirectResponse::create($this->getContinueUrl())->send();
                }
            } catch (\Exception $e) {
                Messages::insert('Login failed, please try again', 'warn');
                RedirectResponse::create($this->getContinueUrl())->send();
            }
            
        }
    }

    /**
     * Handles local logout and SSO (global) logout
     * Redirects user to specific logout URL
     */
    public function setOnLogoutRequest() {
        if($this->request->query->get(\ModuleSSO::LOGOUT_KEY) == 1) {
            unset($_SESSION["uid"]);
            RedirectResponse::create(CFG_DOMAIN_URL)->send();
        } else if($this->request->query->get(\ModuleSSO::GLOBAL_LOGOUT_KEY) == 1) {
            unset($_SESSION["uid"]);
            RedirectResponse::create(CFG_SSO_ENDPOINT_URL . '?' . \ModuleSSO::LOGOUT_KEY . '=1&' . \ModuleSSO::CONTINUE_KEY . '=' . CFG_DOMAIN_URL)->send();
        }
    }
}