<?php
namespace ModuleSSO;

use ModuleSSO\BrowserSniffer;

use ModuleSSO\LoginMethod\ClassicLogin\NoScriptLogin;
use ModuleSSO\LoginMethod\ClassicLogin\IframeLogin;
use ModuleSSO\LoginMethod\CORSLogin;

use ModuleSSO\LoginMethod\ThirdPartyLogin\FacebookLogin;

use Lcobucci\JWT\Signer\Keychain;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Parser;

class Client extends \ModuleSSO
{
    private $publicKey = '';
    private $loginMethod = null;
    
    public function __construct($pubKeyPath = 'app/config/pk.pub')
    {
        $this->publicKey = file_get_contents($pubKeyPath);
    }
    
    public function getUser()
    {
        if($_SESSION['uid']) {
            $query = \Database::$pdo->prepare("SELECT * FROM users WHERE id = ?");
            $query->execute(array($_SESSION['uid']));
            return $query->fetch();
        } else {
            return null;
        }
        
    }
    
    public function getContinueUrl()
    {
        //load server path from db
        $base = CFG_DOMAIN_URL;
        $rqu = "";
        if(!empty($_SERVER['REQUEST_URI'])) {
            $rqu = $_SERVER['REQUEST_URI'];
            $result = parse_url($rqu);
            $path = "";
            if(isset($result['path'])) {
                $path = $result['path'];
                return  $base . $path;
            } else {
                return $base;
            }
        } else {
            return $base;
        }
    }
    
    public function pickLoginMethod()
    {
        if(isset($_GET[\ModuleSSO::FORCED_METHOD_KEY])) {
            $m = $_GET[\ModuleSSO::FORCED_METHOD_KEY];
            if($m == NoScriptLogin::METHOD_NUMBER) {
                $this->loginMethod = new NoScriptLogin();
            } else if($m == IframeLogin::METHOD_NUMBER) {
                $this->loginMethod = new IframeLogin();
            } else if($m == CORSLogin::METHOD_NUMBER) {
                $this->loginMethod = new CORSLogin();
            } else if($m == FacebookLogin::METHOD_NUMBER) {
                $this->loginMethod = new FacebookLogin();
            } else {
                 $this->loginMethod = new NoScriptLogin();
            }
            return;
        }
        
        //CORS supported browsers
        //http://caniuse.com/#feat=cors
        $supportedBrowsers = array(
            'chrome' => 31,
            'ie' => 10,
            'edge' => 12,
            'firefox' => 37,
            'safari' => 6.1,
            'opera' => 12.1, 
        );
        
        //config
        global $loginMethodPriorities;
        foreach ($loginMethodPriorities as $method) {
            if($method === 'cors') {
                $browser = new BrowserSniffer();
                if(isset($supportedBrowsers[$browser->getName()])) {
                    if($browser->getVersion() >= $supportedBrowsers[$browser->getName()]) {
                        $this->loginMethod = new CORSLogin();
                        break;
                    }
                }   
            }
            else if($method === 'iframe') {
                $this->loginMethod = new IframeLogin();
                break;
            }
            else if($method === 'noscript') {
                $this->loginMethod = new NoScriptLogin();
                break;
            }
        } 
    }
    
    public function login() {
        if(isset($_GET[\ModuleSSO::TOKEN_KEY])) {
            $urlToken = $_GET[\ModuleSSO::TOKEN_KEY];
            try {
                $token = (new Parser())->parse((string) $urlToken);
                $signer = new Sha256();
                $keychain = new Keychain();
            
                if($token->verify($signer, $keychain->getPublicKey($this->publicKey)) && $token->getClaim('exp') > time()) {
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
                            header("Location: " .  $this->getContinueUrl());
                            exit();
                        }
                    }
                    
                } else {
                    $this->insertMessage('warn', 'Login failed, please try again');
                    header("Location: " .  $this->getContinueUrl());
                    exit();
                }
            } catch (Exception $e) {
                $this->insertMessage('warn', 'Login failed, please try again');
                header("Location: " .  $this->getContinueUrl());
                exit();
            }
            
        }
    }
    
    public function logout() {
        if(isset($_GET[\ModuleSSO::LOGOUT_KEY]) && $_GET[\ModuleSSO::LOGOUT_KEY] == 1) {
            unset($_SESSION["uid"]);
            header("Location: " . CFG_DOMAIN_URL);
            exit();
        } else if(isset($_GET[\ModuleSSO::GLOBAL_LOGOUT_KEY]) && $_GET[\ModuleSSO::GLOBAL_LOGOUT_KEY] == 1) {
            unset($_SESSION["uid"]);
            header("Location: " . CFG_SSO_ENDPOINT_URL . '?' . \ModuleSSO::LOGOUT_KEY . '=1&' . \ModuleSSO::CONTINUE_KEY . '=' . CFG_DOMAIN_URL);
            exit();
        }
    }
    
    public function run()
    {
        $this->login();
        $this->logout();
    }
    
    public function insertMessage($text, $class = 'success')
    {
        $_SESSION[\ModuleSSO::MESSAGES_KEY][] = array('class' => $class, 'text' => $text);
    }
    
    public function showMessages()
    {
        if(!empty($_SESSION[\ModuleSSO::MESSAGES_KEY])) {
            $str = '';
            foreach ($_SESSION[\ModuleSSO::MESSAGES_KEY] as $k => $message) {
                $str .= '<div class="message ' . $message['class'] . '">' . $message['text'] . '</div>';
                unset($_SESSION[\ModuleSSO::MESSAGES_KEY][$k]);
            }
            return $str;
        } else {
            return;
        }
    }
    
    public function appendScripts()
    {
        echo $this->loginMethod->appendScripts();
    }
    
    public function showLoginMethod() {
        echo $this->loginMethod->showClientLogin($this->getContinueUrl());
    }
}