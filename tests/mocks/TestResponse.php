<?php

namespace sergeymakinen\yii\jwtuser\tests\mocks;

use yii\web\Response;

class TestResponse extends Response
{
    /**
     * @inheritDoc
     */
    public function getCookies()
    {
        return TestCookieCollection::getInstance();
    }
}
