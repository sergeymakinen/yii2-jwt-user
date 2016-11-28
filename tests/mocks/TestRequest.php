<?php

namespace sergeymakinen\tests\mocks;

use yii\web\Request;

class TestRequest extends Request
{
    /**
     * @inheritDoc
     */
    public function getCookies()
    {
        return CookieCollectionSingleton::getInstance();
    }
}
