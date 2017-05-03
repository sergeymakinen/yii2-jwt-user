<?php

namespace sergeymakinen\yii\jwtuser\tests;

use Firebase\JWT\JWT;
use Lcobucci\JWT\Token;
use sergeymakinen\yii\jwtuser\tests\mocks\TestCookieCollection;
use sergeymakinen\yii\jwtuser\tests\mocks\TestGlobals;
use sergeymakinen\yii\jwtuser\tests\mocks\TestIdentity;
use sergeymakinen\yii\jwtuser\tests\mocks\TestRequest;
use sergeymakinen\yii\jwtuser\tests\mocks\TestResponse;
use sergeymakinen\yii\jwtuser\User;
use yii\helpers\ArrayHelper;
use yii\web\Cookie;

/**
 * @coversDefaultClass \sergeymakinen\yii\jwtuser\User
 * @covers ::<private>
 */
class UserTest extends TestCase
{
    protected static $successfulSets = [
        '[false,{"useAuthKey":false,"appendAuthKey":false},"kfoo","kfoo",0,0,3600,"jti_foo","jti_bar","iss",{},"aud",{}]',
        '[false,{"useAuthKey":false,"appendAuthKey":false},"kfoo","kfoo",0,0,3600,"jti_foo","jti_bar","iss",{},"http:\/\/example.com",null]',
        '[false,{"useAuthKey":false,"appendAuthKey":false},"kfoo","kfoo",0,0,3600,"jti_foo","jti_bar","http:\/\/example.com",null,"aud",{}]',
        '[false,{"useAuthKey":false,"appendAuthKey":false},"kfoo","kfoo",0,0,3600,"jti_foo","jti_bar","http:\/\/example.com",null,"http:\/\/example.com",null]',
    ];

    public function claimsProvider()
    {
        $configs = [
            [true, [
                'useAuthKey' => false,
                'appendAuthKey' => false,
            ]],
            [true, [
                'useAuthKey' => true,
                'appendAuthKey' => false,
            ]],
            [true, [
                'useAuthKey' => true,
                'appendAuthKey' => true,
            ]],
        ];
        $keys = [
            [true, 'kfoo', 'kfoo'],
            [false, 'kfoo', 'kbar'],
        ];
        $dateClaims = [
            // 'valid', 'iat', 'nbf', 'exp'
            [true, 0, 0, 3600],
            [true, 0, 0, 3600],

            [false, 3600, 0, 3600],                     // iss > now
            [false, 0, 61, 3600],                       // nbf > now
            [false, 0, 0, 59],                          // exp < now
        ];
        $jtis = [
            [true, 'jti_foo', 'jti_foo'],
            [false, 'jti_foo', 'jti_bar'],
        ];
        $issuerClosure = function () {
            return 'iss';
        };
        $issuers = [
            [true, 'iss', $issuerClosure],
            [true, 'http://example.com', null],
            [false, 'issfoo', 'iss'],
            [false, 'issfoo', $issuerClosure],
        ];
        $audienceClosure = function () {
            return 'aud';
        };
        $audiences = [
            [true, 'aud', $audienceClosure],
            [true, 'http://example.com', null],
            [false, 'audbaz', 'aud'],
            [false, 'audbaz', $audienceClosure],
        ];
        $claims = [[]];
        foreach ([$configs, $keys, $dateClaims, $jtis, $issuers, $audiences] as $sets) {
            $newClaims = [];
            foreach ($claims as $claim) {
                $valid = array_shift($claim);
                foreach ($sets as $set) {
                    $setValid = array_shift($set);
                    $newValid = ($valid !== null ? $valid : true) && $setValid;
                    $newClaim = array_merge([$newValid], $claim, $set);
                    $newClaims[json_encode($newClaim)] = $newClaim;
                }
            }
            $claims = $newClaims;
        }
        return $claims;
    }

    /**
     * @covers ::getIdentityAndDurationFromCookie
     * @dataProvider claimsProvider
     */
    public function testGetIdentityAndDurationFromCookie(
        $success,
        array $config,
        $key,
        $testKey,
        $issuedAt,
        $notBefore,
        $expiresAt,
        $id,
        $testId,
        $issuer,
        $testIssuer,
        $audience,
        $testAudience
    )
    {
        $set = json_encode(func_get_args());
        if (in_array($set, static::$successfulSets, true)) {
            $success = true;
        }

        $now = time();
        $this->bootApplication(['components' => ['user' => array_merge($config, [
            'key' => $testKey,
            'issuer' => $testIssuer,
            'audience' => $testAudience,
        ])]]);
        $this->sendReferenceTokenAndGetClaims(
            $now, $key, $issuedAt, $notBefore, $expiresAt, $id, $issuer, $audience
        );
        TestIdentity::$authKeys[$id] = substr($testId, 4);
        TestGlobals::$time = $now + 60;
        $result = $this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndDurationFromCookie');
        if ($success) {
            $this->assertSame(substr($testId, 4), $result['identity']->authKey);
            $this->assertSame($expiresAt - $notBefore, $result['duration']);
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * @dataProvider claimsProvider
     */
    public function testGetIdentityAndTokenFromCookie(
        $success,
        array $config,
        $key,
        $testKey,
        $issuedAt,
        $notBefore,
        $expiresAt,
        $id,
        $testId,
        $issuer,
        $testIssuer,
        $audience,
        $testAudience
    )
    {
        $set = json_encode(func_get_args());
        if (in_array($set, static::$successfulSets, true)) {
            $success = true;
        }

        $now = time();
        $this->bootApplication(['components' => ['user' => array_merge($config, [
            'key' => $testKey,
            'issuer' => $testIssuer,
            'audience' => $testAudience,
        ])]]);
        $claims = $this->sendReferenceTokenAndGetClaims(
            $now, $key, $issuedAt, $notBefore, $expiresAt, $id, $issuer, $audience
        );
        TestIdentity::$authKeys[$id] = substr($testId, 4);
        TestGlobals::$time = $now + 60;
        try {
            $result = $this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndTokenFromCookie');
        } catch (\Exception $e) {
            $result = null;
        }
        if ($success) {
            /** @var TestIdentity $identity */
            /** @var Token $token */
            list($identity, $token) = $result;
            $this->assertSame(substr($testId, 4), $identity->authKey);
            $this->assertEquals($claims, $this->getClaimsFromToken($token));
        } else {
            $this->assertNull($result);
        }
    }

    public function loginProvider()
    {
        $configs = [
            [[
                'useAuthKey' => false,
                'appendAuthKey' => false,
            ]],
            [[
                'useAuthKey' => true,
                'appendAuthKey' => false,
            ]],
            [[
                'useAuthKey' => true,
                'appendAuthKey' => true,
            ]],
        ];
        $keys = [
            [true, 'foo', 'foo'],
            [false, 'foo', 'bar'],
        ];
        $durations = [
            [0],
            [1],
            [59],
            [3600],
        ];
        $jtis = [
            ['jti_foo'],
            ['jti_bar'],
        ];
        $issuerClosure = function () {
            return 'iss';
        };
        $issuers = [
            ['iss', 'iss'],
            ['iss', $issuerClosure],
            ['http://example.com', null],
        ];
        $audienceClosure = function () {
            return 'aud';
        };
        $audiences = [
            ['aud', 'aud'],
            ['aud', $audienceClosure],
            ['http://example.com', null],
        ];
        $claims = [[]];
        foreach ([$configs, $keys, $durations, $jtis, $issuers, $audiences] as $sets) {
            $newClaims = [];
            foreach ($claims as $claim) {
                $valid = array_shift($claim);
                foreach ($sets as $set) {
                    $setValid = is_bool($set[0]) ? array_shift($set) : true;
                    $newValid = ($valid !== null ? $valid : true) && $setValid;
                    $newClaim = array_merge([$newValid], $claim, $set);
                    $newClaims[json_encode($newClaim)] = $newClaim;
                }
            }
            $claims = $newClaims;
        }
        return $claims;
    }

    /**
     * @covers ::sendIdentityCookie
     * @covers ::renewIdentityCookie
     * @dataProvider loginProvider
     */
    public function testSendAndRenewIdentityCookie(
        $success,
        array $config,
        $key,
        $renewKey,
        $duration,
        $id,
        $expectedIssuer,
        $issuer,
        $expectedAudience,
        $audience
    )
    {
        $now = time();
        $this->bootApplication(['components' => ['user' => array_merge($config, [
            'key' => $key,
            'issuer' => $issuer,
            'audience' => $audience,
        ])]]);
        TestGlobals::$time = $now;
        $identity = TestIdentity::findIdentity($id);
        $this->invokeInaccessibleMethod($this->getUser(), 'sendIdentityCookie', [$identity, $duration]);
        $referenceClaims = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $duration,
            'jti' => $id,
            'iss' => $expectedIssuer,
            'aud' => $expectedAudience,
        ];
        if ($duration === 0) {
            unset($referenceClaims['exp']);
        }
        if ($this->getUser()->useAuthKey) {
            $authKey = substr($id, 4);
            if ($this->getUser()->appendAuthKey) {
                $key .= $authKey;
            } else {
                $referenceClaims['authKey'] = $authKey;
            }
        }
        $this->assertEquals($referenceClaims, $this->getReferenceClaims($now, $key));

        $now += $duration > 0 ? $duration - 1 : 0;
        TestGlobals::$time = $now;
        $oldReferenceClaims = $referenceClaims;
        $referenceClaims['nbf'] = $now;
        if ($duration > 0) {
            $referenceClaims['exp'] = $now + $duration;
        }
        $this->getUser()->key = $renewKey;
        $this->invokeInaccessibleMethod($this->getUser(), 'renewIdentityCookie');
        $claims = $this->getReferenceClaims($now, $key);
        if ($success) {
            $this->assertEquals($referenceClaims, $claims);
        } else {
            $this->assertEquals($oldReferenceClaims, $claims);
        }
    }

    public function testGetIdentityAndTokenFromCookieWithNoCookie()
    {
        $this->bootApplication();
        $this->assertNull($this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndTokenFromCookie'));
    }

    public function testGetIdentityAndTokenFromCookieWithNoIdentity()
    {
        $this->bootApplication();
        $issuer = \Yii::$app->getRequest()->getHostInfo();

        $this->sendReferenceTokenAndGetClaims(time(), 'foobar', 0, 0, 60, 'non-existent', $issuer, $issuer);
        $this->assertNull($this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndTokenFromCookie'));

        $this->bootApplication([
            'components' => [
                'user' => [
                    'useAuthKey' => true,
                    'appendAuthKey' => true,
                ],
            ],
        ]);
        $this->sendReferenceTokenAndGetClaims(time(), 'foobar', 0, 0, 60, 'non-existent', $issuer, $issuer);
        $this->assertNull($this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndTokenFromCookie'));
    }

    /**
     * @expectedException \yii\base\InvalidValueException
     */
    public function testGetIdentityAndTokenFromCookieWithWrongIdentity()
    {
        $this->bootApplication();
        $issuer = \Yii::$app->getRequest()->getHostInfo();
        $this->sendReferenceTokenAndGetClaims(time(), 'foobar', 0, 0, 60, 'error', $issuer, $issuer);
        $this->invokeInaccessibleMethod($this->getUser(), 'getIdentityAndTokenFromCookie');
    }

    public function methodNamesProvider()
    {
        return [
            ['getIdentityAndTokenFromCookie'],
            ['getIdentityAndDurationFromCookie'],
            ['renewIdentityCookie'],
        ];
    }

    /**
     * @dataProvider methodNamesProvider
     * @expectedException \yii\base\InvalidValueException
     */
    public function testGetIdentitiesWithNoKey($methodName)
    {
        $this->bootApplication([
            'components' => [
                'user' => [
                    'key' => '',
                ],
            ],
        ]);
        $issuer = \Yii::$app->getRequest()->getHostInfo();
        $this->sendReferenceTokenAndGetClaims(time(), 'foobar', 0, 0, 60, 'non-existent', $issuer, $issuer);
        $this->invokeInaccessibleMethod($this->getUser(), $methodName);
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return \Yii::$app->user;
    }

    /**
     * @param int $currentTime
     * @param string $key
     * @return array
     */
    protected function getReferenceClaims($currentTime, $key)
    {
        JWT::$timestamp = $currentTime;
        return (array) JWT::decode(
            \Yii::$app->request->cookies->getValue($this->getUser()->identityCookie['name']),
            $key,
            ['HS256']
        );
    }

    protected function sendReferenceTokenAndGetClaims(
        $currentTime,
        $key,
        $issuedAt,
        $notBefore,
        $expiresAt,
        $id,
        $issuer,
        $audience
    )
    {
        $issuedAt += $currentTime;
        $notBefore += $currentTime;
        $expiresAt += $currentTime;
        $claims = [
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'exp' => $expiresAt,
            'jti' => $id,
            'iss' => $issuer,
            'aud' => $audience,
        ];
        if ($this->getUser()->useAuthKey) {
            $authKey = substr($id, 4);
            if ($this->getUser()->appendAuthKey) {
                $key .= $authKey;
            } else {
                $claims['authKey'] = $authKey;
            }
        }
        $this->sendReferenceToken($claims, $key);
        return $claims;
    }

    protected function sendReferenceToken(array $claims, $key)
    {
        $cookie = new Cookie($this->getUser()->identityCookie);
        $cookie->expire = $claims['exp'];
        $cookie->value = JWT::encode($claims, $key, 'HS256');
        \Yii::$app->getResponse()->getCookies()->add($cookie);
    }

    protected function getClaimsFromToken(Token $token)
    {
        $claims = [];
        foreach (array_keys($token->getClaims()) as $name) {
            $claims[$name] = $token->getClaim($name);
        }
        return $claims;
    }

    protected function bootApplication(array $config = [])
    {
        TestGlobals::$time = null;
        TestIdentity::$authKeys = null;
        TestCookieCollection::getInstance()->removeAll();
        $this->createWebApplication(ArrayHelper::merge([
            'components' => [
                'user' => [
                    'class' => User::className(),
                    'identityClass' => TestIdentity::className(),
                    'key' => 'foobar',
                    'enableAutoLogin' => true,
                ],
                'request' => [
                    'class' => TestRequest::className(),
                ],
                'response' => [
                    'class' => TestResponse::className(),
                ],
            ],
        ], $config));
    }
}
