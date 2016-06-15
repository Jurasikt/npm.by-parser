# npm.by-parser
Авто отслеживание и бронирование свободных мест на сайте npm.by

![img](https://raw.githubusercontent.com/Jurasikt/npm.by-parser/master/link.png)

## Установка

1. Скачайте phar архив npm.php `wget https://github.com/Jurasikt/npm.by-parser/raw/master/npm.php`
2. Создайте sqlite database. Имя файла бд - .npm.db . Скрипт для создания таблиц install.sql
3. Создайте крон задачу `* * * * * php /путь/до/файла/npm.php > /var/log/npm.log 2>&1`

## Использование

Используйте графический интерфейс

## Licenses

MIT
