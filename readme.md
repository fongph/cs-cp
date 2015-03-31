## Инструменты
 - composer
 - bower
 - grunt
 - php_intl.so || php_intl.dll 
 - browscap.ini
 

## Установка development версии
 - создать файл ***development.config.php*** по принципу ***development.config.php.sample***
 - выполнить генерацию билда ```php cp.php build 0 -e development```

## Активизация демо версии
 - выполняем генерацию билда с параметром ```-d``` и предаем id пользователя которым будем выполнять автоматическую авторизацию ```php cp.php build 0 -d 100```
 - выгружаем данные авторизации пользователя ```php cp.php load-demo-user```. Команда создает файл demoUserData.php в корне и пишет туда данные авторизации нашего пользователя.

## Компиляция less и js файлов
 - javascript файлы находятся в app/assets/javascript
 - файлы стилей - app/assets/stylesheets
 - в папке static/javascript, static/stylesheets находятся уже скомпилированные файлы, изменять их нужно только посредством компиляции.
 - компиляцию выполняем инструментом grunt, запуская его с корневой директории проекта ```grunt```. Инструмент выполняет компиляцию стилей сжатие в один файл, соединение js скриптов в один файл и их минификацию.
 - также есть возможность запустить grunt в режиме слежки за файлами ```grunt watch```. При каждом изменении файлов стиля или javascript файла будет происходить компиляция без сжатия.
 - перед комитом изменений нужно производить компиляцию, так как ```grunt watch``` не минифицирует файлы, которые используются на production окружении
 - grunt ожидает библиотеки bower'a, это означает, что перед компиляцией нужно установить компоненты bower'a командой ```bower install```
 - пока вот так...