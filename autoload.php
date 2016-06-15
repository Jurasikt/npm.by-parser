<?php
spl_autoload_register(
    function ($class){
        $path = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR;

        if (file_exists("$path$class.php")) {
            require_once $path . $class . '.php';
        }
    }
);