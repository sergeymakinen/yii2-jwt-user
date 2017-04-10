<?php

namespace sergeymakinen\yii\jwtuser\tests\mocks;

use yii\web\Request;

class TestRequest extends Request
{
    /**
     * @inheritDoc
     */
    public function getCookies()
    {
        return TestCookieCollection::getInstance();
    }
}
