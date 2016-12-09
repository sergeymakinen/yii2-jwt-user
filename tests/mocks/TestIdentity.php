<?php

namespace sergeymakinen\tests\web\mocks;

use yii\base\InvalidCallException;
use yii\web\IdentityInterface;

class TestIdentity implements IdentityInterface
{
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new InvalidCallException('Mocked');
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'jti';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        throw new InvalidCallException('Mocked');
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        throw new InvalidCallException('Mocked');
    }
}
