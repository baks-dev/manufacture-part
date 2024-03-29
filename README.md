# BaksDev Manufacture Part

[![Version](https://img.shields.io/badge/version-7.0.19-blue)](https://github.com/baks-dev/manufacture-part/releases)
![php 8.2+](https://img.shields.io/badge/php-min%208.1-red.svg)

Модуль производства партий продукции

## Установка

``` bash
$ composer require baks-dev/manufacture-part
```

## Дополнительно

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

Установка файловых ресурсов в публичную директорию (javascript, css, image ...):

``` bash
$ php bin/console baks:assets:install
```

Тесты

``` bash
$ php bin/phpunit --group=manufacture-part
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

