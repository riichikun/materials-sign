# BaksDev Material Sign

[![Version](https://img.shields.io/badge/version-7.3.19-blue)](https://github.com/baks-dev/materials-sign/releases)
![php 8.4+](https://img.shields.io/badge/php-min%208.4-red.svg)
[![packagist](https://img.shields.io/badge/packagist-green)](https://packagist.org/packages/baks-dev/materials-sign)

Модуль Честный знак сырья

## Установка

``` bash
composer require \
phpoffice/phpspreadsheet
baks-dev/barcode
baks-dev/materials-sign
```

Добавить директорию и установить права для загрузки файлов:

``` bash
sudo mkdir <path_to_project>/public/upload/material_sign_code
sudo chown -R unit:unit <path_to_project>/public/upload/material_sign_code
```

Установка приложения для обрезки из PDF пустые области:

```bash
sudo apt install pdftk imagemagick texlive-extra-utils
```

* Для запуска pdfcrop от пользователя sudo:

```bash
sudo visudo
```

добавить строку

```text
unit ALL=(ALL) NOPASSWD: /usr/bin/pdfcrop
```

сохранить изменения Ctrl+X -> Y

* Pазрешить работу с PDF, изменив в файле /etc/ImageMagick-6/policy.xml и перезапустить web-сервер

```html
<policy domain="coder" rights="none" pattern="PDF"/>
```

на

```html

<policy domain="coder" rights="read|write" pattern="PDF"/>
```

## Дополнительно

Установка конфигурации и файловых ресурсов:

``` bash
$ php bin/console baks:assets:install
```

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

## Тестирование

``` bash
$ php bin/phpunit --group=materials-sign
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.
