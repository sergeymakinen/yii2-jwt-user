<?php

namespace sergeymakinen\tests\web\mocks;

use yii\web\Request;

class TestRequest extends Request
{
    /**
     * {@inheritdoc}
     */
    public function getCookies()
    {
        return CookieCollectionSingleton::getInstance();
    }
}
