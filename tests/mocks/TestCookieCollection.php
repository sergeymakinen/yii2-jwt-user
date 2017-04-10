<?php

namespace sergeymakinen\yii\jwtuser\tests\mocks;

use yii\base\Object;
use yii\web\CookieCollection;

class TestCookieCollection extends Object
{
    /**
     * @var CookieCollection
     */
    private static $_instance;

    /**
     * @return CookieCollection
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new CookieCollection();
        }
        return self::$_instance;
    }
}
