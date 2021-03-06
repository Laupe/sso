<?php
namespace ModuleSSO\EndPoint\LoginMethod\Other;

use ModuleSSO\EndPoint\LoginMethod;
use ModuleSSO\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class CORSLogin
 * @package ModuleSSO\EndPoint\LoginMethod\Other
 */
class CORSLogin extends LoginMethod
{
    /**
     * @var int Number of login method
     */
    const METHOD_NUMBER = 3;

    /**
     * If email and password field are set i $_GET returns JSON with JWT, otherwise returns just JSON with status and code
     *
     * @overrides LoginMethod::setOnLoginRequest()
     *
     * @uses CORSLogin::getOrigin()
     * @uses CORSLogin::originIsValid()
     * @uses LoginMethod::setOrUpdateSSOCookie()
     * @uses JWT::generate()
     * @uses JsonResponse::create()
     */
    public function setOnLoginRequest()
    {
        $origin = $this->getOrigin();
        if ($this->originIsValid($origin)) {
            header('Access-Control-Allow-Origin: ' . $this->request->server->get('HTTP_ORIGIN'));
            header('Content-Type: application/json');
            header('Access-Control-Allow-Credentials: true');

            if ($this->request->get('email') && $this->request->get('password')) {
                $email = $this->request->get('email');
                $password = $this->request->get('password');

                $query = \Database::$pdo->prepare("SELECT * FROM users WHERE email = ?");
                $query->execute(array($email));
                $user = $query->fetch();
                if ($user && \ModuleSSO::verifyPasswordHash($password, $user['password'])) {
                    $this->setOrUpdateSSOCookie($user['id']);
                    $token = (new JWT($this->getDomain()))->generate(array('uid' => $user['id']));

                    //JsonResponse or json_encode does not work here
                    echo '{"status":"ok","' . \ModuleSSO::TOKEN_KEY . '":"' . $token . '"}';
                } else {
                    JsonResponse::create(array("status" => "fail", "code" => "user_not_found"))->send();
                }
            } else {
                JsonResponse::create(array("status" => "fail", "code" => "bad_login"))->send();
            }
        } else {
            JsonResponse::create(array("status" => "fail", "code" => "http_origin_not_set_or_invalid"))->send();
        }
    }

    /**
     * {@inheritdoc}
     *
     * Checks origin of request.
     * Binds loginListener or checkCookieListener if origin host is allowed
     *
     * @uses CORSLogin::setOnLoginRequest()
     * @uses CORSLogin::setOnCheckCookieRequest()
     * @uses JsonResponse::create()
     */
    public function perform()
    {
        $origin = $this->getOrigin();
        if ($this->originIsValid($origin)) {
            $parsed = parse_url($this->request->server->get('HTTP_ORIGIN'));
            if(isset($parsed['host'])) {
                if($this->request->get(\ModuleSSO::LOGIN_KEY) == 1) {
                    $this->setOnLoginRequest();
                } else if($this->request->get(\ModuleSSO::CHECK_COOKIE_KEY) == 1) {
                    $this->setOnCheckCookieRequest();
                } else {
                    JsonResponse::create(array("status" => "fail", "code" => "key_not_recognized"))->send();
                }
            } else {
                //URL does not contain host
                JsonResponse::create(array("status" => "fail", "code" => "bad_continue_url"))->send();
            }
        } else {
            //probably won't reach this because of Same origin policy
            JsonResponse::create(array("status" => "fail", "code" => "http_origin_not_set_or_invalid"))->send();
        }
    }

    /**
     * Checks if SSO cookie is set and tries to get user from that cookie
     *
     * @uses CORSLogin::getOrigin()
     * @uses CORSLogin::originIsValid()
     * @uses LoginMethod::getUserFromCookie()
     * @uses JWT::generate()
     */
    public function setOnCheckCookieRequest()
    {
        $origin = $this->getOrigin();
        if ($this->originIsValid($origin)) {
            header('Access-Control-Allow-Origin: ' . $this->request->server->get('HTTP_ORIGIN'));
            header('Access-Control-Allow-Credentials: true');
            header('Content-Type: application/json');
            if($user = $this->getUserFromCookie()) {
                $token = (new JWT($this->getDomain()))->generate(array('uid' => $user['id']));
                echo '{"status":"ok","' . \ModuleSSO::TOKEN_KEY . '":"' . $token . '","email":"' . $user['email'] . '"}';
            } else {
                JsonResponse::create(array("status" => "fail", "code" => "bad_cookie"))->send();
            }
        } else {
            //probably won't reach this because of Same origin policy
            JsonResponse::create(array("status" => "fail", "code" => "http_origin_not_set"))->send();
        }
    }

    /**
     * Obtains origin from HTTP_ORIGIN or HTTP_HOST
     *
     * @return mixed|null|string
     */
    protected function getOrigin()
    {
        $origin = $this->request->server->get('HTTP_ORIGIN');
        $host = $this->request->server->get('HTTP_HOST');
        return $origin ? $origin : ($host ? 'http://' . str_replace('www.', '', $host) : null);
    }

    /**
     * Checks in whitelist if origin is valid
     *
     * @param $origin
     * @return bool
     *
     * @uses LoginMethod::isInWhiteList()
     */
    protected function originIsValid($origin)
    {
        $parsed = parse_url($origin);
        if (isset($parsed['host']) && $this->isInWhiteList($parsed['host'])) {
            return true;
        } else {
            return false;
        }
    }
}
