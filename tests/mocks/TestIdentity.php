<?php

namespace sergeymakinen\tests\mocks;

use yii\base\InvalidCallException;
use yii\web\IdentityInterface;

class TestIdentity implements IdentityInterface
{
    /**
     * @inheritDoc
     */
    public static function findIdentity($id)
    {
        if ($id === 'jti') {
            return new self();
        } elseif ($id === 'error') {
            return new \stdClass();
        } else {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new InvalidCallException('Mocked');
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return 'jti';
    }

    /**
     * @inheritDoc
     */
    public function getAuthKey()
    {
        throw new InvalidCallException('Mocked');
    }

    /**
     * @inheritDoc
     */
    public function validateAuthKey($authKey)
    {
        throw new InvalidCallException('Mocked');
    }
}
