<?php


define('DOCROOT', __DIR__ . DIRECTORY_SEPARATOR);

require_once DOCROOT. 'autoload.php';

$ps = new NPMParser();
if (preg_match('/cli/', php_sapi_name())) {
    $task = new Task($ps);
    $task->run();
    die;
}

$message = null;
$token = md5(microtime(true));
setcookie('token', $token);

if (array_key_exists('npm', $_POST)) {

    try {

        if (@$_COOKIE['token'] != @$_POST['npm']['token']) {
            throw new Exception("Csrf token failed");   
        }

        setcookie('token', '');
        $task = new Task($ps);
        $task->createTask($_POST['npm']);
        $message = 'The task been created successfully.';

    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

View::factory()
    ->bind('stations', $ps->generateStation())
    ->bind('message', $message)
    ->bind('token', $token)
    ->response('main');
