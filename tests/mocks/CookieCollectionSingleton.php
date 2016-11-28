<?php

namespace sergeymakinen\tests\mocks;

use yii\web\CookieCollection;

class CookieCollectionSingleton
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
        if (!isset(self::$_instance)) {
            self::$_instance = new CookieCollection();
        }
        return self::$_instance;
    }
}
