# JWT powered User for Yii 2

JWT ([JSON Web Token](https://jwt.io)) identification for Yii 2 [User](http://www.yiiframework.com/doc-2.0/yii-web-user.html) component.

[![Code Quality](https://img.shields.io/scrutinizer/g/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://scrutinizer-ci.com/g/sergeymakinen/yii2-jwt-user) [![Packagist Version](https://img.shields.io/packagist/v/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-jwt-user) [![Total Downloads](https://img.shields.io/packagist/dt/sergeymakinen/yii2-jwt-user.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-jwt-user) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

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
