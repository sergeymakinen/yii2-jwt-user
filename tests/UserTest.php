<?php

namespace sergeymakinen\tests\web;

use Firebase\JWT\JWT;
use sergeymakinen\tests\web\mocks\CookieCollectionSingleton;
use sergeymakinen\tests\web\mocks\TestIdentity;
use sergeymakinen\web\User;
use yii\web\Cookie;

class UserTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->createWebApplication([
            'components' => [
                'user' => [
                    'class' => 'sergeymakinen\web\User',
                    'identityClass' => 'sergeymakinen\tests\web\mocks\TestIdentity',
                    'token' => 'foobar',
                ],
                'request' => [
                    'class' => 'sergeymakinen\tests\web\mocks\TestRequest',
                ],
                'response' => [
                    'class' => 'sergeymakinen\tests\web\mocks\TestResponse',
                ],
            ],
        ]);
        CookieCollectionSingleton::getInstance()->removeAll();
    }

    public function testGetAudience()
    {
        $this->assertEquals(\Yii::$app->request->hostInfo, $this->getAudience());
        $this->getUser()->audience = 'foo';
        $this->assertEquals('foo', $this->getAudience());
        $this->getUser()->audience = function () {
            return 'bar';
        };
        $this->assertEquals('bar', $this->getAudience());
    }

    public function testTokensProvider()
    {
        return [
            // 'valid', 'both|reference|non-reference', 'iat', 'nbf', 'exp', 'jti', 'iss', 'aud'
            [[true, 'both', 0, 0, 3600, 'jti', 'iss', 'aud']],
            [[false, 'both', 3600, 0, 3600, 'jti', 'iss', 'aud']],                    // iss > now
            [[false, 'both', 0, 61, 3600, 'jti', 'iss', 'aud']],                      // nbf > now
            [[false, 'both', 0, 0, 59, 'jti', 'iss', 'aud']],                         // exp < now
            [[true, 'reference', 0, 0, 3600, 'jti', 'iss', ['aud', 'aud1']]],         // aud mismatch
            [[false, 'nonReference', 0, 0, 3600, 'jti', 'iss', ['aud', 'aud1']]],     // aud mismatch
        ];
    }

    /**
     * @dataProvider testTokensProvider
     * @param array $token
     */
    public function testGetTokenClaimsAndSetToken(array $token)
    {
        $scopes = [
            'both' => [true, false],
            'reference' => [true],
            'nonReference' => [false],
        ];
        $now = time();
        $valid = array_shift($token);
        $scope = $scopes[array_shift($token)];
        foreach ([0, 1, 2] as $claim) {
            $token[$claim] = $now + $token[$claim];
        }
        foreach ($scope as $reference) {
            foreach (['normal', 'wrongKey', 'notSet'] as $mode) {
                CookieCollectionSingleton::getInstance()->removeAll();
                if ($mode !== 'notSet') {
                    $this->setToken($reference, $this->prepareTestToken($token, 0));
                }
                if ($mode === 'wrongKey') {
                    $oldKey = $this->getUser()->token;
                    $this->getUser()->token = 'invalidKey';
                }
                $result = $this->getTokenClaims(
                    count($scope) === 1 ? $reference : !$reference, $now + 60, $this->prepareTestToken($token, 1)[5]
                );
                if ($mode === 'wrongKey') {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $this->getUser()->token = $oldKey;
                }
                if ($mode === 'normal' && $valid) {
                    $this->assertEquals(
                        array_combine(['iat', 'nbf', 'exp', 'jti', 'iss', 'aud'], $this->prepareTestToken($token, 0)),
                        $result
                    );
                } else {
                    $this->assertFalse($result);
                }
            }
        }
    }

    public function testLoginByCookieOk()
    {
        $now = time();
        $this->getUser()->autoRenewCookie = false;
        $this->assertTrue($this->getUser()->isGuest);
        $this->setToken(false, [$now, $now, $now + 3600, 'jti', 'iss', $this->getAudience()]);
        $this->invokeInaccessibleMethod($this->getUser(), 'loginByCookie');
        $this->assertFalse($this->getUser()->isGuest);
        $this->assertEquals($now + 3600, CookieCollectionSingleton::getInstance()->get($this->getUser()->identityCookie['name'])->expire);
    }

    public function testLoginByCookieOkNoExpiration()
    {
        $now = time();
        $this->getUser()->autoRenewCookie = false;
        $this->assertTrue($this->getUser()->isGuest);
        $this->setToken(false, [$now, $now, 0, 'jti', 'iss', $this->getAudience()]);
        $this->invokeInaccessibleMethod($this->getUser(), 'loginByCookie');
        $this->assertFalse($this->getUser()->isGuest);
        $this->assertEquals(0, CookieCollectionSingleton::getInstance()->get($this->getUser()->identityCookie['name'])->expire);
    }

    public function testLoginByCookieNotFound()
    {
        $this->invokeInaccessibleMethod($this->getUser(), 'loginByCookie');
        $this->assertTrue($this->getUser()->isGuest);

        $now = time();
        $this->getUser()->autoRenewCookie = false;
        $this->assertTrue($this->getUser()->isGuest);
        $this->setToken(false, [$now, $now, $now + 3600, 'notFound', 'iss', $this->getAudience()]);
        $this->invokeInaccessibleMethod($this->getUser(), 'loginByCookie');
        $this->assertTrue($this->getUser()->isGuest);
    }

    /**
     * @expectedException \yii\base\InvalidValueException
     */
    public function testLoginByCookieError()
    {
        $now = time();
        $this->getUser()->autoRenewCookie = false;
        $this->assertTrue($this->getUser()->isGuest);
        $this->setToken(false, [$now, $now, $now + 3600, 'error', 'iss', $this->getAudience()]);
        $this->invokeInaccessibleMethod($this->getUser(), 'loginByCookie');
    }

    public function testRenewIdentityCookie()
    {
        $this->invokeInaccessibleMethod($this->getUser(), 'renewIdentityCookie');
        $this->assertEquals(0, CookieCollectionSingleton::getInstance()->count);

        $now = time();
        $this->invokeInaccessibleMethod($this->getUser(), 'sendIdentityCookie', [new TestIdentity(), 60]);
        $this->assertTrue(CookieCollectionSingleton::getInstance()->has($this->getUser()->identityCookie['name']));
        sleep(1);
        $this->invokeInaccessibleMethod($this->getUser(), 'renewIdentityCookie');
        $this->assertGreaterThan($now + 60, CookieCollectionSingleton::getInstance()->get($this->getUser()->identityCookie['name'])->expire);
    }

    public function testSendIdentityCookie()
    {
        $now = time();
        $this->invokeInaccessibleMethod($this->getUser(), 'sendIdentityCookie', [new TestIdentity(), 60]);
        $this->assertGreaterThanOrEqual($now + 60, CookieCollectionSingleton::getInstance()->get($this->getUser()->identityCookie['name'])->expire);

        CookieCollectionSingleton::getInstance()->removeAll();
        $this->invokeInaccessibleMethod($this->getUser(), 'sendIdentityCookie', [new TestIdentity(), 0]);
        $this->assertEquals(0, CookieCollectionSingleton::getInstance()->get($this->getUser()->identityCookie['name'])->expire);
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return \Yii::$app->user;
    }

    /**
     * @return string
     */
    protected function getAudience()
    {
        return $this->invokeInaccessibleMethod($this->getUser(), 'getAudience');
    }

    /**
     * @param $reference
     * @param int $currentTime
     * @param string $audience
     *
     * @return array|false
     */
    protected function getTokenClaims($reference, $currentTime = null, $audience = null)
    {
        if (!$reference) {
            return $this->invokeInaccessibleMethod($this->getUser(), 'getTokenClaims', [$currentTime, $audience]);
        }

        JWT::$timestamp = $currentTime;
        try {
            return (array) JWT::decode(
                \Yii::$app->request->cookies->getValue($this->getUser()->identityCookie['name']),
                $this->getUser()->token,
                ['HS256']
            );
        } catch (\Exception $e) {
            JWT::$timestamp = null;
        }
        \Yii::$app->getResponse()->getCookies()->remove(new Cookie($this->getUser()->identityCookie));
        return false;
    }

    /**
     * @param bool $reference
     * @param array $params
     */
    protected function setToken($reference, array $params)
    {
        list($issuedAt, $notBefore, $expiresAt, $id, $issuer, $audience) = $params;
        if (!$reference) {
            $this->invokeInaccessibleMethod($this->getUser(), 'setToken', [
                $issuedAt, $notBefore, $expiresAt, $id, $issuer, $audience,
            ]);
        }

        $cookie = new Cookie($this->getUser()->identityCookie);
        $token = [
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'jti' => $id,
            'iss' => $issuer,
            'aud' => $audience,
        ];
        if ($expiresAt > 0) {
            $token['exp'] = $expiresAt;
            $cookie->expire = $expiresAt;
        }
        $cookie->value = JWT::encode($token, $this->getUser()->token, 'HS256');
        \Yii::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * @param array $token
     * @param int $index
     *
     * @return array
     */
    protected function prepareTestToken(array $token, $index)
    {
        $newToken = [];
        foreach ($token as $value) {
            if (is_array($value)) {
                $value = $value[$index];
            }
            $newToken[] = $value;
        }
        return $newToken;
    }
}
