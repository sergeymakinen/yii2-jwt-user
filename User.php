<?php
/**
 * JWT powered User for Yii 2
 *
 * @see       https://github.com/sergeymakinen/yii2-jwt-user
 * @copyright Copyright (c) 2016 Sergey Makinen (https://makinen.ru)
 * @license   https://github.com/sergeymakinen/yii2-jwt-user/blob/master/LICENSE The MIT License
 */

namespace sergeymakinen\web;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\ValidationData;
use yii\base\InvalidValueException;
use yii\web\Cookie;
use yii\web\IdentityInterface;

/**
 * User class with a JWT cookie as a backend.
 *
 * @see https://jwt.io
 * @see https://tools.ietf.org/html/rfc7519
 * @see \yii\web\User
 */
class User extends \yii\web\User
{
    /**
     * JWT sign key. Must be random and secret.
     *
     * @var string
     * @see https://tools.ietf.org/html/rfc7519#section-11
     */
    public $token;

    /**
     * JWT audience claim ("aud").
     *
     * @var \Closure|string
     * @see https://tools.ietf.org/html/rfc7519#section-4.1.3
     * @since 1.1
     */
    public $audience;

    /**
     * @inheritDoc
     */
    protected function loginByCookie()
    {
        $claims = $this->getTokenClaims();
        if ($claims === false) {
            return;
        }

        /** @var IdentityInterface $class */
        $class = $this->identityClass;
        $identity = $class::findIdentity($claims['jti']);
        if (!isset($identity)) {
            return;
        } elseif (!$identity instanceof IdentityInterface) {
            throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
        }

        if (isset($claims['exp'])) {
            $duration = $claims['exp'] - $claims['nbf'];
        } else {
            $duration = 0;
        }
        if ($this->beforeLogin($identity, true, $duration)) {
            $this->switchIdentity($identity, $this->autoRenewCookie ? $duration : 0);
            $id = $claims['jti'];
            $ip = \Yii::$app->getRequest()->getUserIP();
            \Yii::info("User '{$id}' logged in from {$ip} via the JWT cookie.", __METHOD__);
            $this->afterLogin($identity, true, $duration);
        }
    }

    /**
     * @inheritDoc
     */
    protected function renewIdentityCookie()
    {
        $claims = $this->getTokenClaims();
        if ($claims === false) {
            return;
        }

        $now = time();
        $expiresAt = $now + ($claims['exp'] - $claims['nbf']);
        $this->setToken($claims['iat'], $now, $expiresAt, $claims['jti'], $claims['iss'], $claims['aud']);
    }

    /**
     * @inheritDoc
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        $now = time();
        if ($duration > 0) {
            $expiresAt = $now + $duration;
        } else {
            $expiresAt = 0;
        }
        $this->setToken($now, $now, $expiresAt, $identity->getId());
    }

    /**
     * Tries to read, verify, validate and return a JWT token stored in the identity cookie.
     *
     * @return array|false
     * @since 1.1
     */
    protected function getTokenClaims()
    {
        $jwt = \Yii::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);
        if (!isset($jwt)) {
            return false;
        }

        try {
            $token = (new Parser())->parse($jwt);
            if (!$token->verify(new Sha256(), $this->token)) {
                throw new InvalidValueException('Invalid signature');
            }

            if (!$token->validate($this->initClaims(new ValidationData()))) {
                throw new InvalidValueException('Invalid claims');
            }
            $claims = [];
            foreach (array_keys($token->getClaims()) as $name) {
                $claims[$name] = $token->getClaim($name);
            }
            return $claims;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $ip = \Yii::$app->getRequest()->getUserIP();
        \Yii::warning("Invalid JWT cookie from {$ip}: {$error}.", __METHOD__);
        \Yii::$app->getResponse()->getCookies()->remove(new Cookie($this->identityCookie));
        return false;
    }

    /**
     * Writes a JWT token into the identity cookie.
     *
     * @param int $issuedAt
     * @param int $notBefore
     * @param int $expiresAt
     * @param mixed $id
     * @param string $issuer
     * @param string $audience
     *
     * @since 1.1
     */
    protected function setToken($issuedAt, $notBefore, $expiresAt, $id, $issuer = null, $audience = null)
    {
        $builder = $this->initClaims(new Builder(), $issuer, $audience)
            ->setIssuedAt($issuedAt)
            ->setNotBefore($notBefore)
            ->setId($id);
        $cookie = new Cookie($this->identityCookie);
        if ($expiresAt > 0) {
            $builder->setExpiration($expiresAt);
            $cookie->expire = $expiresAt;
        }
        $cookie->value = (string) $builder
            ->sign(new Sha256(), $this->token)
            ->getToken();
        \Yii::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * Returns a JWT audience claim ("aud").
     *
     * @return string
     * @since 1.1
     */
    protected function getAudience()
    {
        if (is_string($this->audience)) {
            return $this->audience;
        } elseif ($this->audience instanceof \Closure) {
            return call_user_func($this->audience);
        } else {
            return \Yii::$app->getRequest()->getHostInfo();
        }
    }

    /**
     * Returns Builder/ValidationData with "iss" and "aud" claims set.
     *
     * @param Builder|ValidationData $object
     * @param string $issuer
     * @param string $audience
     *
     * @return Builder|ValidationData
     */
    private function initClaims($object, $issuer = null, $audience = null)
    {
        if ($object instanceof Builder) {
            $object->setIssuer(isset($issuer) ? $issuer : \Yii::$app->getRequest()->getHostInfo());
        }
        return $object->setAudience(isset($audience) ? $audience : $this->getAudience());
    }
}
