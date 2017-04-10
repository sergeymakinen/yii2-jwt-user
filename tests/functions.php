<?php

namespace sergeymakinen\yii\jwtuser;

use sergeymakinen\yii\jwtuser\tests\mocks\TestGlobals;

function time()
{
    if (TestGlobals::$time !== null) {
        return TestGlobals::$time;
    }

    return \time();
}
