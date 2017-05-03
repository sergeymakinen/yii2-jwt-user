<?php
/**
 * JWT powered User for Yii 2
 *
 * @see       https://github.com/sergeymakinen/yii2-jwt-user
 * @copyright Copyright (c) 2016-2017 Sergey Makinen (https://makinen.ru)
 * @license   https://github.com/sergeymakinen/yii2-jwt-user/blob/master/LICENSE MIT License
 */

namespace sergeymakinen\yii\jwtuser;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
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
     * @var string JWT sign key. Must be random and secret.
     * @see https://tools.ietf.org/html/rfc7519#section-11
     * @since 3.0
     */
    public $key;

    /**
     * @var bool whether to use a [[IdentityInterface::getAuthKey()]] value to validate a token.
     * @since 3.0
     */
    public $useAuthKey = true;

    /**
     * @var bool whether to append a [[IdentityInterface::getAuthKey()]] value to the sign key or store it as a claim.
     * @since 3.0
     */
    public $appendAuthKey = false;

    /**
     * @var \Closure|string JWT audience claim ("aud").
     * @see https://tools.ietf.org/html/rfc7519#section-4.1.3
     * @since 1.1
     */
    public $audience;

    /**
     * @var \Closure|string JWT issuer claim ("iss").
     * @see https://tools.ietf.org/html/rfc7519#section-4.1.1
     * @since 3.0
     */
    public $issuer;

    /**
     * @inheritDoc
     */
    protected function renewIdentityCookie()
    {
        try {
            /** @var IdentityInterface $identity */
            /** @var Token $token */
            list($identity, $token) = $this->getIdentityAndTokenFromCookie();
            if ($identity === null) {
                return;
            }
        } catch (\Exception $e) {
            if ($e instanceof InvalidValueException) {
                throw $e;
            }

            return;
        }
        $now = time();
        $builder = $this->createBuilderFromToken($token)
            ->setNotBefore($now);
        if ($token->hasClaim('exp')) {
            $builder->setExpiration($now + ($token->getClaim('exp') - $token->getClaim('nbf')));
        }
        $this->sendToken($builder, $identity);
    }

    /**
     * @inheritDoc
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        $now = time();
        $builder = (new Builder())
            ->setIssuedAt($now)
            ->setNotBefore($now)
            ->setId($identity->getId());
        if ($duration > 0) {
            $builder->setExpiration($now + $duration);
        }
        $issuer = $this->getPrincipal($this->issuer);
        if ($issuer !== null) {
            $builder->setIssuer($issuer);
        }
        $audience = $this->getPrincipal($this->audience);
        if ($audience !== null) {
            $builder->setAudience($audience);
        }
        if ($this->useAuthKey && !$this->appendAuthKey) {
            $builder->set('authKey', $identity->getAuthKey());
        }
        $this->sendToken($builder, $identity);
    }

    /**
     * @inheritDoc
     */
    protected function getIdentityAndDurationFromCookie()
    {
        try {
            /** @var IdentityInterface $identity */
            /** @var Token $token */
            list($identity, $token) = $this->getIdentityAndTokenFromCookie();
        } catch (\Exception $e) {
            if ($e instanceof InvalidValueException) {
                throw $e;
            }

            $ip = \Yii::$app->getRequest()->getUserIP();
            $error = lcfirst($e->getMessage());
            \Yii::warning("Invalid JWT cookie from $ip: $error", __METHOD__);
            $this->removeIdentityCookie();
            return null;
        }
        if ($identity === null) {
            $this->removeIdentityCookie();
            return null;
        }

        return ['identity' => $identity, 'duration' => $token->hasClaim('exp') ? $token->getClaim('exp') - $token->getClaim('nbf') : 0];
    }

    /**
     * @return array|null
     */
    private function getIdentityAndTokenFromCookie()
    {
        $value = \Yii::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);
        if ($value === null) {
            return null;
        }

        $token = (new Parser())->parse($value);
        if ($this->useAuthKey && $this->appendAuthKey) {
            $identity = $this->getIdentityFromToken($token);
            if ($identity === null) {
                return null;
            }

            $this->assertSignature($token, $identity);
            $this->assertClaims($token);
        } else {
            $this->assertSignature($token);
            $this->assertClaims($token);
            $identity = $this->getIdentityFromToken($token);
            if ($identity === null) {
                return null;
            }
        }
        return [$identity, $token];
    }

    /**
     * @param \Closure|string|null $value
     * @return string|null
     */
    private function getPrincipal($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \Closure) {
            return $value();
        }

        return \Yii::$app->getRequest()->getHostInfo();
    }

    /**
     * @param IdentityInterface|null $identity
     * @return string
     */
    private function getKey(IdentityInterface $identity = null)
    {
        $key = (string) $this->key;
        if ($this->useAuthKey && $this->appendAuthKey) {
            $key .= $identity->getAuthKey();
        }
        if ($key === '') {
            throw new InvalidValueException('Sign key cannot be empty.');
        }

        return $key;
    }

    /**
     * @param Token $token
     * @param IdentityInterface|null $identity
     */
    private function assertSignature(Token $token, IdentityInterface $identity = null)
    {
        $key = $identity === null ? $this->getKey() : $this->getKey($identity);
        if (!$token->verify(new Sha256(), $key)) {
            throw new \InvalidArgumentException('Invalid signature');
        }
    }

    /**
     * @param Token $token
     */
    private function assertClaims(Token $token)
    {
        $validationData = new ValidationData(time());
        $issuer = $this->getPrincipal($this->issuer);
        if ($issuer !== null) {
            $validationData->setIssuer($issuer);
        }
        $audience = $this->getPrincipal($this->audience);
        if ($audience !== null) {
            $validationData->setAudience($audience);
        }
        if (!$token->validate($validationData)) {
            throw new \InvalidArgumentException('Invalid claims');
        }
    }

    /**
     * @param Token $token
     * @return IdentityInterface|null
     */
    private function getIdentityFromToken(Token $token)
    {
        /* @var $class IdentityInterface */
        $class = $this->identityClass;
        $id = $token->getClaim('jti');
        $identity = $class::findIdentity($id);
        if ($identity === null) {
            return null;
        }

        if (!$identity instanceof IdentityInterface) {
            throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
        }

        if ($this->useAuthKey && !$this->appendAuthKey) {
            $authKey = $token->getClaim('authKey');
            if (!$identity->validateAuthKey($authKey)) {
                \Yii::warning("Invalid auth key attempted for user '$id': $authKey", __METHOD__);
                return null;
            }
        }

        return $identity;
    }

    /**
     * @param Token $token
     * @return Builder
     */
    private function createBuilderFromToken(Token $token)
    {
        $builder = new Builder();
        foreach (array_keys($token->getClaims()) as $name) {
            $builder->set($name, $token->getClaim($name));
        }
        return $builder;
    }

    /**
     * @param Builder $builder
     * @param IdentityInterface $identity
     */
    private function sendToken(Builder $builder, IdentityInterface $identity)
    {
        $cookie = new Cookie($this->identityCookie);
        $cookie->expire = $builder->getToken()->getClaim('exp', '0');
        $cookie->value = (string) $builder
            ->sign(new Sha256(), $this->getKey($identity))
            ->getToken();
        \Yii::$app->getResponse()->getCookies()->add($cookie);
    }
}
