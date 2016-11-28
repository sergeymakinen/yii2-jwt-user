# JWT powered User for Yii 2

JWT ([JSON Web Token](https://jwt.io)) based Yii 2 [User](http://www.yiiframework.com/doc-2.0/yii-web-user.html) component drop-in replacement.

[![Code Quality](https://img.shields.io/scrutinizer/g/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://scrutinizer-ci.com/g/sergeymakinen/yii2-jwt-user) [![Build Status](https://img.shields.io/travis/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://travis-ci.org/sergeymakinen/yii2-jwt-user) [![Code Coverage](https://img.shields.io/codecov/c/github/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://codecov.io/gh/sergeymakinen/yii2-jwt-user) [![SensioLabsInsight](https://img.shields.io/sensiolabs/i/edadf97f-95ba-4998-b832-ed30ca6e1014.svg?style=flat-square)](https://insight.sensiolabs.com/projects/edadf97f-95ba-4998-b832-ed30ca6e1014)

[![Packagist Version](https://img.shields.io/packagist/v/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-jwt-user) [![Total Downloads](https://img.shields.io/packagist/dt/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-jwt-user) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run

```
php composer.phar require sergeymakinen/yii2-jwt-user "^1.0"
```

or add

```
"sergeymakinen/yii2-jwt-user": "^1.0"
```

to the require section of your `composer.json` file.

## Usage

Set the following Yii 2 configuration parameters:

```php
'components' => [
    'user' => [
        'class' => 'sergeymakinen\web\User',
        'identityClass' => 'app\models\User',
        'enableAutoLogin' => true, // Optional
        'token' => 'random key (CHANGE IT!)',
    ],
],
```

Also set `identityClass` to whatever your identity class name is and change `token` to some *random* value and make sure it's *secret*.

That's it!
