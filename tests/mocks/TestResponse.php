<?php

namespace sergeymakinen\tests\mocks;

use yii\web\Response;

class TestResponse extends Response
{
    /**
     * @inheritDoc
     */
    public function getCookies()
    {
        return CookieCollectionSingleton::getInstance();
    }
}
