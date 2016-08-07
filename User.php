<?php

namespace sergeymakinen\web;

use Firebase\JWT\JWT;
use yii\base\InvalidValueException;
use yii\web\Cookie;
use yii\web\IdentityInterface;
use yii\web\User as BaseUser;

class User extends BaseUser
{
    /**
     * @var array the configuration of the identity cookie. This property is used only when [[enableAutoLogin]] is true.
     * @see Cookie
     */
    public $identityCookie = [
        'name' => 'identity',
        'httpOnly' => true,
        'secure' => true
    ];

    /**
     * JWT token. Must be random and secret.
     *
     * @var string
     */
    public $token;

    /**
     * @inheritDoc
     */
    protected function loginByCookie()
    {
        try {
            $value = (array) JWT::decode(\Yii::$app->request->cookies->getValue($this->identityCookie['name']), $this->token, ['HS256']);
        } catch (\Exception $e) {
            return;
        }

        /**
         * @var IdentityInterface $class
         */
        $class = $this->identityClass;
        $identity = $class::findIdentity($value['jti']);
        if ($identity === null) {
            return;
        } elseif (!$identity instanceof IdentityInterface) {
            throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
        }

        if (isset($value['exp'])) {
            $duration = $value['exp'] - $value['nbf'];
        } else {
            $duration = 0;
        }
        if ($this->beforeLogin($identity, true, $duration)) {
            $this->switchIdentity($identity, $this->autoRenewCookie ? $duration : 0);
            $ip = \Yii::$app->request->userIP;
            \Yii::info("User '{$value['jti']}' logged in from $ip via cookie.", __METHOD__);
            $this->afterLogin($identity, true, $duration);
        }
    }

    /**
     * @inheritDoc
     */
    protected function renewIdentityCookie()
    {
        try {
            $value = (array) JWT::decode(\Yii::$app->request->cookies->getValue($this->identityCookie['name']), $this->token, ['HS256']);
        } catch (\Exception $e) {
            return;
        }

        $now = time();
        $value['exp'] = $now + ($value['exp'] - $value['nbf']);
        $value['nbf'] = $now;
        $cookie = new Cookie($this->identityCookie);
        $cookie->expire = $value['exp'];
        $cookie->value = JWT::encode($value, $this->token, 'HS256');
        \Yii::$app->response->cookies->add($cookie);
    }

    /**
     * @inheritDoc
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        $now = time();
        $value = [
            'iss' => \Yii::$app->request->hostInfo,
            'aud' => \Yii::$app->request->hostInfo,
            'nbf' => $now,
            'iat' => $now,
            'jti' => $identity->getId()
        ];
        if ($duration > 0) {
            $value['exp'] = $now + $duration;
        }
        $cookie = new Cookie($this->identityCookie);
        if (isset($value['exp'])) {
            $cookie->expire = $value['exp'];
        }
        $cookie->value = JWT::encode($value, $this->token, 'HS256');
        \Yii::$app->response->cookies->add($cookie);
    }
}
