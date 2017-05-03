<?php

namespace sergeymakinen\yii\jwtuser\tests\mocks;

use yii\base\InvalidCallException;
use yii\base\Object;
use yii\web\IdentityInterface;

class TestIdentity extends Object implements IdentityInterface
{
    /**
     * @var string[]
     */
    public static $authKeys;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $authKey;

    /**
     * @inheritDoc
     */
    public static function findIdentity($id)
    {
        if (strpos($id, 'jti_') === 0) {
            if (isset(self::$authKeys[$id])) {
                $authKey = self::$authKeys[$id];
            } else {
                $authKey = substr($id, 4);
            }
            return new self([
                'id' => $id,
                'authKey' => $authKey,
            ]);
        }

        if ($id === 'error') {
            return new \stdClass();
        }

        return null;
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
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * @inheritDoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->authKey === $authKey;
    }
}
