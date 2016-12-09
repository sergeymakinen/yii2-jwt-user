<?php

namespace sergeymakinen\tests\web\mocks;

use yii\web\Response;

class TestResponse extends Response
{
    /**
     * {@inheritdoc}
     */
    public function getCookies()
    {
        return CookieCollectionSingleton::getInstance();
    }
}
