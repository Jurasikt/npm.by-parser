<?php

$web = 'index.php';

if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', 0)) {
Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());
Phar::webPhar(null, $web);
include 'phar://' . __FILE__ . '/' . Extract_Phar::START;
return;
}

if (@(isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST'))) {
Extract_Phar::go(true);
$mimes = array(
'phps' => 2,
'c' => 'text/plain',
'cc' => 'text/plain',
'cpp' => 'text/plain',
'c++' => 'text/plain',
'dtd' => 'text/plain',
'h' => 'text/plain',
'log' => 'text/plain',
'rng' => 'text/plain',
'txt' => 'text/plain',
'xsd' => 'text/plain',
'php' => 1,
'inc' => 1,
'avi' => 'video/avi',
'bmp' => 'image/bmp',
'css' => 'text/css',
'gif' => 'image/gif',
'htm' => 'text/html',
'html' => 'text/html',
'htmls' => 'text/html',
'ico' => 'image/x-ico',
'jpe' => 'image/jpeg',
'jpg' => 'image/jpeg',
'jpeg' => 'image/jpeg',
'js' => 'application/x-javascript',
'midi' => 'audio/midi',
'mid' => 'audio/midi',
'mod' => 'audio/mod',
'mov' => 'movie/quicktime',
'mp3' => 'audio/mp3',
'mpg' => 'video/mpeg',
'mpeg' => 'video/mpeg',
'pdf' => 'application/pdf',
'png' => 'image/png',
'swf' => 'application/shockwave-flash',
'tif' => 'image/tiff',
'tiff' => 'image/tiff',
'wav' => 'audio/wav',
'xbm' => 'image/xbm',
'xml' => 'text/xml',
);

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$basename = basename(__FILE__);
if (!strpos($_SERVER['REQUEST_URI'], $basename)) {
chdir(Extract_Phar::$temp);
include $web;
return;
}
$pt = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], $basename) + strlen($basename));
if (!$pt || $pt == '/') {
$pt = $web;
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $_SERVER['REQUEST_URI'] . '/' . $pt);
exit;
}
$a = realpath(Extract_Phar::$temp . DIRECTORY_SEPARATOR . $pt);
if (!$a || strlen(dirname($a)) < strlen(Extract_Phar::$temp)) {
header('HTTP/1.0 404 Not Found');
echo "<html>\n <head>\n  <title>File Not Found<title>\n </head>\n <body>\n  <h1>404 - File ", $pt, " Not Found</h1>\n </body>\n</html>";
exit;
}
$b = pathinfo($a);
if (!isset($b['extension'])) {
header('Content-Type: text/plain');
header('Content-Length: ' . filesize($a));
readfile($a);
exit;
}
if (isset($mimes[$b['extension']])) {
if ($mimes[$b['extension']] === 1) {
include $a;
exit;
}
if ($mimes[$b['extension']] === 2) {
highlight_file($a);
exit;
}
header('Content-Type: ' .$mimes[$b['extension']]);
header('Content-Length: ' . filesize($a));
readfile($a);
exit;
}
}

class Extract_Phar
{
static $temp;
static $origdir;
const GZ = 0x1000;
const BZ2 = 0x2000;
const MASK = 0x3000;
const START = 'index.php';
const LEN = 6685;

static function go($return = false)
{
$fp = fopen(__FILE__, 'rb');
fseek($fp, self::LEN);
$L = unpack('V', $a = (binary)fread($fp, 4));
$m = (binary)'';

do {
$read = 8192;
if ($L[1] - strlen($m) < 8192) {
$read = $L[1] - strlen($m);
}
$last = (binary)fread($fp, $read);
$m .= $last;
} while (strlen($last) && strlen($m) < $L[1]);

if (strlen($m) < $L[1]) {
die('ERROR: manifest length read was "' .
strlen($m) .'" should be "' .
$L[1] . '"');
}

$info = self::_unpack($m);
$f = $info['c'];

if ($f & self::GZ) {
if (!function_exists('gzinflate')) {
die('Error: zlib extension is not enabled -' .
' gzinflate() function needed for zlib-compressed .phars');
}
}

if ($f & self::BZ2) {
if (!function_exists('bzdecompress')) {
die('Error: bzip2 extension is not enabled -' .
' bzdecompress() function needed for bz2-compressed .phars');
}
}

$temp = self::tmpdir();

if (!$temp || !is_writable($temp)) {
$sessionpath = session_save_path();
if (strpos ($sessionpath, ";") !== false)
$sessionpath = substr ($sessionpath, strpos ($sessionpath, ";")+1);
if (!file_exists($sessionpath) || !is_dir($sessionpath)) {
die('Could not locate temporary directory to extract phar');
}
$temp = $sessionpath;
}

$temp .= '/pharextract/'.basename(__FILE__, '.phar');
self::$temp = $temp;
self::$origdir = getcwd();
@mkdir($temp, 0777, true);
$temp = realpath($temp);

if (!file_exists($temp . DIRECTORY_SEPARATOR . md5_file(__FILE__))) {
self::_removeTmpFiles($temp, getcwd());
@mkdir($temp, 0777, true);
@file_put_contents($temp . '/' . md5_file(__FILE__), '');

foreach ($info['m'] as $path => $file) {
$a = !file_exists(dirname($temp . '/' . $path));
@mkdir(dirname($temp . '/' . $path), 0777, true);
clearstatcache();

if ($path[strlen($path) - 1] == '/') {
@mkdir($temp . '/' . $path, 0777);
} else {
file_put_contents($temp . '/' . $path, self::extractFile($path, $file, $fp));
@chmod($temp . '/' . $path, 0666);
}
}
}

chdir($temp);

if (!$return) {
include self::START;
}
}

static function tmpdir()
{
if (strpos(PHP_OS, 'WIN') !== false) {
if ($var = getenv('TMP') ? getenv('TMP') : getenv('TEMP')) {
return $var;
}
if (is_dir('/temp') || mkdir('/temp')) {
return realpath('/temp');
}
return false;
}
if ($var = getenv('TMPDIR')) {
return $var;
}
return realpath('/tmp');
}

static function _unpack($m)
{
$info = unpack('V', substr($m, 0, 4));
 $l = unpack('V', substr($m, 10, 4));
$m = substr($m, 14 + $l[1]);
$s = unpack('V', substr($m, 0, 4));
$o = 0;
$start = 4 + $s[1];
$ret['c'] = 0;

for ($i = 0; $i < $info[1]; $i++) {
 $len = unpack('V', substr($m, $start, 4));
$start += 4;
 $savepath = substr($m, $start, $len[1]);
$start += $len[1];
   $ret['m'][$savepath] = array_values(unpack('Va/Vb/Vc/Vd/Ve/Vf', substr($m, $start, 24)));
$ret['m'][$savepath][3] = sprintf('%u', $ret['m'][$savepath][3]
& 0xffffffff);
$ret['m'][$savepath][7] = $o;
$o += $ret['m'][$savepath][2];
$start += 24 + $ret['m'][$savepath][5];
$ret['c'] |= $ret['m'][$savepath][4] & self::MASK;
}
return $ret;
}

static function extractFile($path, $entry, $fp)
{
$data = '';
$c = $entry[2];

while ($c) {
if ($c < 8192) {
$data .= @fread($fp, $c);
$c = 0;
} else {
$c -= 8192;
$data .= @fread($fp, 8192);
}
}

if ($entry[4] & self::GZ) {
$data = gzinflate($data);
} elseif ($entry[4] & self::BZ2) {
$data = bzdecompress($data);
}

if (strlen($data) != $entry[0]) {
die("Invalid internal .phar file (size error " . strlen($data) . " != " .
$stat[7] . ")");
}

if ($entry[3] != sprintf("%u", crc32((binary)$data) & 0xffffffff)) {
die("Invalid internal .phar file (checksum error)");
}

return $data;
}

static function _removeTmpFiles($temp, $origdir)
{
chdir($temp);

foreach (glob('*') as $f) {
if (file_exists($f)) {
is_dir($f) ? @rmdir($f) : @unlink($f);
if (file_exists($f) && is_dir($f)) {
self::_removeTmpFiles($f, getcwd());
}
}
}

@rmdir($temp);
clearstatcache();
chdir($origdir);
}
}

Extract_Phar::go();
__HALT_COMPILER(); ?>Z                 	   index.php�  ��aW�  �TK�         autoload.php�   ��aW�   �,M�         src/NPMParser.php^  ��aW^  Y߶         src/NPMInterface.phpC   ��aWC   Lk^��         src/Task.php  ��aW  ��8�         src/View.phpv  ��aWv  �>�>�         view/main.php  ��aW  �`�|�      	   witch.png�[  ��aW�[  9���      <?php


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
<?php
spl_autoload_register(
    function ($class){
        $path = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR;

        if (file_exists("$path$class.php")) {
            require_once $path . $class . '.php';
        }
    }
);<?php

class NPMParser implements NPMInterface
{
    public function generateStation()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://npm.by/booking/arrival",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "id_station=36&is_waypoint=false",
          CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            throw new Exception('Can not load the station');
        }
        $data = json_decode($response, true);

        $return = [];
        foreach ($data as $value) {
            $return = array_merge($return, $value);
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, "id_station=53&is_waypoint=false");
        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            throw new Exception('Can not load the station');
        }

        foreach (json_decode($response, true) as $value) {
            $return = array_merge($return, $value);
        }

        $ret = array();
        foreach ($return as $value) {
            $ret[] = array(
                'id' => $value['id'],
                'name' => array_key_exists('name', $value) ? $value['name'] : $value['value']
                );
        }

        return $ret;
    }

    public function getPlaces($from, $to, $date)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://npm.by/booking/route-time",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query(array('id_departure_station' => $from, 'departure_is_waypoint' => 0,
                'id_arrival_station' => $to, 'arrival_is_waypoint' => 0, 'date' => $date)),
            CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
            ),
        ));
        $response = curl_exec($curl);

        if (!$response || !json_decode($response)) {
            throw new Exception("Error Processing Request");
        }

        foreach (json_decode($response, true) as $val) {
            if (!empty($val)) {
                return $val;
            }
        }
        return $val;
    }

    /**
     * @param array $intime  = (15, 16, 18, 20);
     *
     * @return param for reserve
     */
    public function getFreePlaces($from, $to, $date, $intime)
    {
        $free = $this->getPlaces($from, $to, $date);
        $return = array();
        foreach($free as $item) {

            if ($item['count'] == 0 || !isset($item['time'])) {
                continue;
            }

            array_map(function($x) use ($item, &$return) {

                if ("$x:00" <= $item['time'] && "$x:60" > $item['time']) {
                    
                    $return[$x] = $item;
                }
                return $x;

            }, $intime);

            //if ($return) break;
        }

        $result = [];
        foreach ($intime as $key) {
            if  (array_key_exists($key, $return)) $result[$key] = $return[$key];
        }
        return $result;
    }

    public function oath($user, $pass)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://npm.by/auth/auth",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => http_build_query(array('password' => $pass, 'phone_code' => substr($user, 0, 2),
                'phone' => substr($user, 2))),
            CURLOPT_HTTPHEADER => array(
            "x-requested-with: XMLHttpRequest"
            ),
        ));
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', curl_exec($curl), $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        return $cookies;
    }

    public function checkSid($sid)
    {
        $curl = curl_init('http://npm.by/account/booking');
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => "SID=$sid",
            CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
        ));
        $result = curl_exec($curl);

        if (!$result) {
            return false;
        }

        return (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 302);
    }

    /**
     * reserve_date 11/06/2016, 14:00,
     * reserve_to 65
     * reserve_from 36 
     *
     */
    public function reserve(array $param, $sid)
    {
        if (!$this->checkSid($sid)) {
            throw new Exception('Unauthorized user');
        }

        $param = array_merge($param, array('book' => '', 'reserve_from_is_waypoint' => 0,
            'reserve_passangers' => 1, 'reserve_to_is_waypoint' => 0));

        $curl = curl_init('http://npm.by/booking/reserve');
        curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIE => "SID=$sid",
                CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POSTFIELDS => http_build_query($param),
                CURLOPT_CUSTOMREQUEST => 'POST',
            ));

        $result = curl_exec($curl);

        if (!$result) {
            throw new Exception("The tickets can not be booked");
        }
        curl_close($curl);

        if (preg_match('/name=\"phone\" value=\"(.+)\"/', $result, $phone) && 
                preg_match('/selected=\"selected\">(.+)</', $result, $code) )
        {
            $code = $code[1];
            $phone = $phone[1];
        } else {
            
            throw new Exception("The tickets can not be booked. Incorrect server response 
                http://npm.by/booking/reserve");
        }

        $cookies = [
            'SID' => $sid,
            'backTicket' => 0,
            'fromIdStation' => $param['reserve_from'],
            'toIdStation' => $param['reserve_to'],
            'dateValueDay' => current(explode(',', $param['reserve_date'])),
            'toIsWaypoint' => 0,
            'fromIsWaypoint' => 0,
        ];

        
        $curl = curl_init('http://npm.by/booking-verification/check-phone');
        curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIE => http_build_query($cookies, null, "; ", PHP_QUERY_RFC3986),
                CURLOPT_TIMEOUT => self::NPM_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POSTFIELDS => http_build_query(['phone_code' => $code, 'phone' => $phone]),
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ["x-requested-with: XMLHttpRequest"],
            ));

        //$result = curl_exec($curl);
        $result = '{"email_available":"NO","status":"OK"}';
        if (!$result) {
            throw new Exception("The tickets can not be booked. Incorrect server response 
                code when http://npm.by/booking-verification/check-phone");

        }

        if ($data = json_decode($result, true) and $data['status'] == 'OK') {
            return true;
        } else {
            throw new Exception("The tickets can not be booked. Incorrect server response: '$result'.");
        }
    }

}<?php 
interface  NPMInterface {
    const NPM_TIMEOUT = 25;
}
<?php 
class Task
{
    protected $_dsn = 'sqlite:' . DOCROOT . '.npm.db';

    protected $_npm;

    protected $_pdo;

    function __construct(NPMInterface $npm)
    {
        $this->_npm = $npm;

        if (Phar::running(false) != '') {
            $this->_dsn = 'sqlite:' . dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . '.npm.db';
        }
        //echo $this->_dsn;
        $this->_pdo = new PDO($this->_dsn);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getNPM()
    {
        return $this->_npm;
    }

    public function createTask(array $post, $check_oath = true)
    {
        if ($this->arr($post, 'phone')) {

            $cookies = $this->getNPM()
                ->oath($this->arr($post, 'phone'), $this->arr($post, 'password'));

            if ($check_oath && !$this->getNPM()->checkSid($this->arr($cookies,'SID'))) {
                throw new Exception('Invalid username or password');
            }

            $query = $this->_pdo->prepare("INSERT into methods (method_email, method_cookies) values (?, ?)");
            $query->bindValue(1, $this->arr($post, 'email'));
            $query->bindValue(2, $cookies['SID']);
            $r = $query->execute();

        } else {
            $query = $this->_pdo->prepare("INSERT into methods (method_email) values (?)");
            $query->bindValue(1, $this->arr($post, 'email'));
            $r = $query->execute();
        }

        $pl = $this->getNPM()->getPlaces(
            $this->arr($post, 'from'),
            $this->arr($post, 'to'),
            $this->arr($post, 'date')
            );
        if (!$pl) {
            throw new Exception('The input data is not correct');
        }

        $date = new DateTime($post['date']);

        $time = preg_split('/[\s,]+/', $this->arr($post, 'time', ''));

        $query = $this->_pdo->prepare("INSERT into task (task_departure_station, task_arrival_station, 
            task_status, method_id, task_date_end, task_date_start, task_time) 
            values (:from_id, :to_id, 0, (select max(method_id) from methods), :date_end, datetime('now'), :task_time)");

        $query->bindValue(':from_id', $this->arr($post, 'from'));
        $query->bindValue(':to_id', $this->arr($post, 'to'));
        $query->bindValue(':date_end', $date->format('Y-m-d'));
        $query->bindValue(':task_time', serialize($time));
        $query->execute();
    }

    

    protected function arr(array $array, $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        return $default;
    }

    /**
     *
     *
     */
    public function run()
    {
        $query = $this->_pdo->prepare("SELECT * from task t1 
                left join methods t2 on t2.method_id = t1.method_id
                where t1.task_status = 0 and t1.task_date_end > date('now', '1 day')
                order by t1.id");
        $query->execute();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            //echo "<br><br>";

            $date = new DateTime($row['task_date_end']);

            $freePlacesMap = $this
                ->getNPM()
                ->getFreePlaces($row['task_departure_station'], $row['task_arrival_station'], 
                    $date->format('d-m-Y'), unserialize($row['task_time']));

            if (empty($freePlacesMap)) {
                $query = $this->_pdo->prepare("UPDATE task set task_cache = 1 where id = ?");
                $query->bindValue(1, $row['id']);
                $query->execute();
                continue;
            }
            
            if ($row['method_cookies'] === null) {

                if (md5(serialize($freePlacesMap)) == $row['task_cache']) {
                    continue;
                }

                $query = $this->_pdo->prepare("UPDATE task set task_cache = ? where id = ?");
                $query->bindValue(1, md5(serialize($freePlacesMap)));
                $query->bindValue(2, $row['id']);
                $query->execute();

                //echo json_encode($freePlacesMap);
                mail($row['method_email'], 'npm.by free places', json_encode($freePlacesMap));
                continue;
            }

            $time = current($freePlacesMap)['time'];
            try {

                $this
                    ->getNPM()
                    ->reserve([
                            'reserve_from' => $row['task_departure_station'],
                            'reserve_to' => $row['task_arrival_station'],
                            'reserve_date' => $date->format('d/m/Y') . ", $time",
                        ], 
                        $row['method_cookies']);

                $query = $this->_pdo->prepare("UPDATE task set task_status = 1 where id = ?");
                $query->bindValue(1, $row['id']);
                $query->execute();

            } catch (Exception $e) {
                //echo $e->getMessage();
                mail($row['method_email'], 'an error occurred... npm.by', $e->getMessage());
                continue;
            }

            //echo 'success';
            mail($row['method_email'], 'npm.by success', json_encode($freePlacesMap));
        }
    }

}<?php
class View 
{
    
    private $_view = DOCROOT.'view/';

    private $_variable =  array();

    public static function factory()
    {
        return new View;
    }


    public function bind($name, $value)
    {
        $this->_variable[$name] = $value;

        return $this;
    }

    public function response($page)
    {
        if (file_exists($this->_view . $page . '.php')) {
            
            extract($this->_variable, EXTR_OVERWRITE);
            ob_start();

            try {
                include $this->_view . $page . '.php';

            } catch (Exception $e) {
                ob_end_clean();
                throw $e;
            }
            
        } else {

            throw new Exception(sprintf('The file %s not found', $this->_view . $page . '.php'));
        }

        ob_end_flush();
    }

}<!DOCTYPE html>
<html>
<head>
    <title>npm.by parser</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
</head>
<body style="background-image: url(witch.png); background-repeat: no-repeat; background-attachment: fixed; background-position: right top;">
<div style="height: 20px;"> </div>
<div class="container">

<?php if ($message !== null): ?>
  <div class="alert alert-info" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <strong> <?php echo $message ?></strong> 
  </div>
<?php endif; ?>

<form action="" method="post">

    
    <div class="col-md-6 col-md-offset-1">
     <h3 style="text-align: center;">ONLINE БРОНИРОВАНИЕ МЕСТ</h3>
    </div>
    <div class="row"></div>
    <div class="col-md-3 col-md-offset-1">
        От куда
        <select class="form-control" name="npm[from]">
        <!-- 88  == 36  -->
            <?php foreach($stations as $item):?>
                <option value="<?php if ($item['id'] == 88) echo 36; else echo $item['id'] ?>">
                <?php echo $item['name'] ?></option>
            <?php endforeach;?>
        </select>
    </div>

    <div class="col-md-3">
        Куда
        <select class="form-control" name="npm[to]">
            <?php foreach($stations as $item):?>
                <option value="<?php echo $item['id'] ?>" > <?php echo $item['name'] ?></option>
            <?php endforeach;?>
        </select>
    </div>

    

    <div class="col-md-6 col-md-offset-1">
    <div style="height:20px"></div>
      <div class="form-group" style="width: 60%;">
        <label data-toggle="tooltip" data-placement="top" title="Для уведомления о наличии свободных мест">
        Электронная почта*</label>
        <input type="email" class="form-control"  placeholder="Email"  name="npm[email]" required>
      </div>
      <div style="height:10px"></div>
      <h4 data-toggle="tooltip" data-placement="top" title="Не обязательно к заполнению. Заполнить только для авто бронирования">Данные для авторизации на npm.by*</h4>
      <p style="color: #aaa; font-size: 0.7em;">Пароль и номер телефона не сохраняется в базе</p>
      <div style="display: flex;">
          
          <div class="form-group" style="padding: 10px;">
            <label>Пароль</label>
            <input type="password" class="form-control" name="npm[password]" placeholder="password">
          </div>

          <div class="form-group" style="padding:10px;">
            <label>Номер телефона (+375 xxxxxxxxx)</label>
            <input type="text" class="form-control" name="npm[phone]" placeholder="Пример заполнения: 331234567">
          </div>
      </div>

      <div class="form-group" style="width: 60%;">
        <label>Дата в формате dd-mm-YYYY</label>
        <input type="text" class="form-control"  placeholder="01-01-2016"  name="npm[date]" required>
      </div>

      <div class="form-group" style="width: 60%;">
        <label>Время отправления(час) в порядке приоритета</label>
        <textarea class="form-control" rows="3" required placeholder="Например 18, 17, 21, 12, 09" name="npm[time]"></textarea>
      </div>
      <input type="hidden" name="npm[token]" value="<?php echo $token ?>">

      <button type="submit" class="btn btn-default">Создать task</button>
    </div>
</form>

</div>

<script type="text/javascript">
$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})
</script>
</body>
</html>�PNG

   IHDR    R   �0�   PLTE   ���������������������������}l�������������DDc���t[����eP���l��_]MiǞw�ʛ���'�����Ǹ�����ֺ3���|^�����尴�h`oaV���چ_|��ɶwSҕ��x�ń�����̹s����>����]Ź�c_tno����Y��QQx�պ_d����sb���ц��SWl��oH~����yup���ʒ�Ϟ��wq~������{�ƾ�د�o�����Ь���k�ҬW�tA-<s�����X��d�����R����ݎ���~����JKSވ��]�iA��N,��Ҿ�L�]�qEi>��=�����ާኸی���∲�_�ܛW���݈���9�x���FHm��Vւ�Z�������xU����rL�[<{�3c>���B�ʑSI������}��c������k�i�ʮ}������s�~Af׽����j:�N2�H+�^��]��|B�i�L�������ϖd����Ͱ"��������Ȥ�j�����Ӣ�Q|)v}�w�귇�E*<;Z�iW���ЋNE=��`I�F3�;$ں�U8mݻ\�����K.��|m�/1�u<�Iu�td�P��}Rq��Mݧx��q��;�İ^<nh8��h�N8B<��nm��z����E����jf^VͬP��D��3kv��ۭ����đ�oa?F>��`c�ۀ�����h�Wb��I���C�Ғ�XFK��􊷵WQ*~2�̌DKIa���׃��ޓ�ty��z�~m�a��ܧĬ�VFk���ejE��a��ų�E�^��X   _tRNS 
&;1HP]�gm������������}�������ɝ������������������ձ������ʴ���������������������ѭ��������H
N  XIIDATx�욽��`���[Ng4�bA�2d(x)	Ɩހ��[H	�d-�Dp�V2�B���B{풭c����s��}�:��?_Q���pX��8O�LS8O��p�#��x2�����L�yNL�.�b�.��[��L8�#-\�(C��!���zY���	�G�v�
JPY�]:�2WU�"��,���B��	Ŭ����B	�K^�U�������������ҵ�T�;{��۷�G�-�Ʀ���4���G�h��;�#����4V�eZ�n���p���<����]|��Ij��ԋ��tF���E�D��Df3�2��z��FD��� ��ֆk`ih7s�NS'�76@F�²��L��J�v��p3XJ�kY�;���^�)5�G�8�ǅ� &�af�j�/�J��r�%f�F�IS�*�f�����gw�P=��1ۿ��F�|�{^.䡗�)�3s�=q�L���;�Bt����E���$1��������P3�O�d$x9�� b-�f�&YF��l4B/D�e�`-E�a�n1e�l�r9�f.�&c}E��<�I2��4r�X��t����M���h�@dv�����XE�]�`0�?�����B 
�[�fN��R0��@Rg���Sz; QF��t8zC��%��H�b�K�l�-z�loMI���̉Q��%=4�^5�o���~���,ͽ4�A̜)1� *�v����oۮ�x�h��8���l�z� ��4�b�H���}מ�K�gY�ۦ2�§u͖%���y�6d���-�A��ֆ��?zbXK����7�E�I��ۭ] ���]��C���|����#@�)��������PL�+1cb�|n�n)�N+�ȶ�C��#�i���,��=�g�%�C�� ��U�2�l�S{z�3���Z�i��b���01@Y����,B1��JXK)��	��w',��b�����I1����6p��pߞ����S�E� )cbě5�I��{����_+��ʇ�?��Ŝ��ؿ���w��m�Q'�,$MG�,Yt�k��X��ڌU�hY�CŮU*�����Z��PD�čЙ�����Jb&5k�Y!�+	妉IBa�aLX\F����y�ؕ�}u�{��޲O��s�s���J���K�����ٝȿg.%�,�H������w#T �kҢz�g��}_#�l|�b��f;-�r`�lJ��U��@ l���� 3���G�Hۆ<t��m�o!0���w��j�����h#��%_�螵�G��0ɰvpMZ��v|NǺL����;,,􄤱-��Y���-�?6gas,���"�r�Dt��(��5��l��3���8]4����K�Z 󆒯`pI,U�j4��Պ"�m��N�+t�W�!���V;>>�R���?hTN`<#�)���k�P�5�k���)-f@&2La�P��h���P�v˽����yEY�i����9n��Q�n�<�!�Z���샣�S��
�,Y��h����T�Pւ�:�n���h%�����,pi� ��:2��ѦP�Z�1��|��v��Y@�x��2�#��0#�^�Nw��:��9�9ȷg�BY���p�<������x�7n�.˘�2����l����l�95v�ŷp�ap��'��,���ZZ #(z�56v�b��h3��ZШC��%�sꔋo=��Z���c�!.>���F}ۮ2Fy'ݵ�v�}�n��3��t|�-�NX�|����=D�8Ck��2oɃ)�e���Z��@;�}��,u�%��)#$\n8�9}���WV,,T�0G�L�3Cl�w�"0���_`������H��;�I^2ML�T�v5 ����M;�ݩ=~-�5��9(xT��
Ͱh8���G��"x� ��Eo�J���;��N�V;zsɲ�-2�L"�������W�Gb��^��V8���I�ʷ�<�2���G/�bmW%���F�Ѥ�Kż@#j%0�1@�"��Z�t�[�X���X�2�Lu���Ht��e\�02c ���+�&�JϏ�V�!����N�0N'������Ůl��HEU����!!�\�8K�=&��d���[{�R?#�6�H��&��s"U�j�3�_�sݔ�R4u+Mݢ��E7K~!�����T�IΓ�$���3h"�`q:˖��҈!���2���hӡ��}Ѵ��I�lca\�ͽi�;ڷ�@"XǓӣI�é��|�-�T��Ђ����n�b��S�EW:�0��|$���h44���)
ά5��uYY���Ѽ�o���5^��+J�@$Q3MM��x0�ɸ	��S�0��T<�����7d������h"1a����|7D��߱E&��ט�Ms��h0�vA�Kg�<��aI}���j��h�	AQyKl���D���4��g��`�Ǫ�ԗ�7gϼ�?�;j�s�s��!`"�,G�����	�B$���������4OL]N$4��7���ձ����6����^�^M��YT�?�t��f�9�l�U�0��S�W�jʘ��PW�
��SS�ȸ];޿&/�1~s�3�V���rt��N��-fv��t�S^� �Q�}v[�5�<�D�R,X������������~a�r�@�.�'�vy.���_��fv��4�g�'Rj�+\��D"N/.����Ll���*�oI,/����>����[i�Ƥ`�t�t����9��h���K�e�A=�%�D�/B)� �c1t���'�x�m�G��l<NQ��266��n��7�Y��Vɪ���F�VzN'V1&�����a��W��l4�2`�r�YX��Z8�D�1gmQ��|��G�Abцz-�Z����Ʈ���kG't��/���&��k1���*�n�f�(Y ��͉6��/	m,�xY�e����k�����S_���H�m�y2`�t�����v�Q4g�q�^�)R��h��d�HY{�{{{��ȼ�������r�;�c�k:v�nL�ͩ 6���I;�^��LCh�V��� ��	�#YO>���o�~�b�:��H�H����$�DI���V Q04����Rw��^إ������c'��ree��Y�ʾ&�e	`���@<8�$2ɀ�w�J���q�?��V���0��2�uauuuaaued�hdeb_S{i6�����d�d{����F�3My��ϡ&��^�1�0�q��2����$�=I 	��8��;\I����Dn�;�K���/q��(ԤNDQ�(�Q8��& ����~����'�=`ca���x(��z�j/�8:|���Za�w��g}H.�h-�`K=�і��3a�%�ȶTm5�T�*;�ŘJ�d��8�X�F�B:���Yp��F��z�`����<m�Y'��j�S�ӕ��j�"���ib��喩~R
5�t�
�I
��Sa���� 	2�Q_�~N�o8�O��=0kdq��V���K+; !.����$�܃��9%�a�;,#��6��smz���A��/CJp�a��ǰ�;f]X��J�tw[Wgf&'A�Γ�s���en�uT\C0�>��+W�g
z�#��Q"�c)V>��KNa�c/%_�r����"ɧ�K8 8N��H�ǘ[�J��kT�~4�N5���xb�T�2՘��%���0�A݀�Vw� 0���[#��	P,�r��F�H���G�����g�2E��M��z2r�O�H�2>{�h��g�*�h�؊������L��3�F��,��#�p�o�jj���#{��{�\>����r<X�p?��%zS�ђ����gk�E�u��0.�{�Tw"�^�������3�+ì2108�<��w{
:Wـ��ǲ�bb��>c?lR�M���D���:�L���{�z��Ara��rw`(�\ �����O�ׁ�9� B0�?z�'�B�&.�'h?�z�s92�{w��G$H�kX�iv��U?�`�N0�IP!0��B���:K`�u33,Ͱ̫���^�@����D����,��φ�{/���.�IF {ي��$$�&g %/�*���䅙�k��:g�Ñ_�6��(~(�۰����s�~�g� �z2�ޚ�w *�׈��i�\�V��X\aJ-�pX����qg�V�q��kkׄ���#��b��������V#x�N/
^ԏ�H�XĠ�8����5��"B�	g�I����Ą�"�j�����-�ta���7i��y��&Kv;?������='d]�.�}�]�dS ���E _�E����M��j��P��'�i6��A�\� �|����z��)Ie���f<��/����_�����ę��Y���g�XsK9�o	�6���� �?$���YN�Ĕ¾�]��x��hu�YJ��
�P(�8#�>4��y٥�~�
�R�em���.-q�4�:�J�2~���� �0� �,�B���U.r8�]�X9��� $$��Î�F��J+��a���LG=�q��[KZS�K�L��G�4��~R����1���y��	g"E��>�/��q���Pp�!�sLGB3���J������݊�G0ٱ�γ���:���H�z�B�1�
.��2K�u�G���}L��:��L&3��	��~8i���5" ��WY{��������r3���^�`rV����:3�[�jL(AP��������{��;���������� ��W�A������Y;"��ڑ�5��cc��d��0-Rp����v㯏wu���s�cs;��jC9g���J�1"����|��c�+ꩈc��Cv���5�h<�%���v_2�_�o)}���P��^&�;�G.���Ջ򉑑|�� e%w������o������) 1�rt�˅��h| 3ꜙ�!*,8� ��`��T=���]���a�PT+�2�d�"h�ab�b�.(�`UTVV`�]r/0�z���a�(�0����00Җ�/~�R��Y>�v��Ŝ�?];đ�vtt�����X�<.�8@`��f�Q�Q�Q5C/�y9U]^Vq�� �*�BJ|ɥ��p�����ELEMo�#u�UJ��.0"Pț;��##o��1�S��ng7�7��]�xq*�8f�����ΏB1��<3M�\�00H�� �y[��
��1	��6�@!�0M���iM�Ņ��t���_R���W=�E__s�I=��++����I'#j7?�on�ߟ���H�-�d����/w��3a<���ke�qX1_��ŧ��:�5� �	0�Iҡ��doU��-O�0��`��j�B2-
�Ү A������h�����
@sp@�^&�ð!�$ZҢ�V*m���?��|q���ٮ�7����	xOl~r��EH0��+�� 0�;�U��e�F�]��%U��Q6��ܼZ��B2Y��>R�Y#����HƬ�7ך� ���G�j����!*r�9��xf���n>zuqq`�2�0��8
o�ywr�B�����8�` s�~B�A>a���������/il��0���)��l	�H��;d~ȗ�U�ed�1֊B�.1��5��D�gČ9�6��*w�����7�b���4C:��ff��/����C�f��b�
�J�<�������v~���O'����ǔaFF�)��6����A����0�c}F��*���-UUm�<�I�$���K�E=�dF�Q���z1I����߯/�!�B��s���~<�'�hCⲢŸ=*a^v��g._ۊo�v/����z��c��(Ks�B�kľR.�ۚe��l��062���gBQ�I"Q���Ít"�xܝ=O�/�o>���g����k��!0:]ֻtbx1��P"L0���#T@Yc?�ؖ��:�6����U�flc��n�+͏�0'�5v���$�n�3�#�V�b*���'8L/wd37��A7���ɫ׮E������nkh��۔\����]ą�J�ҵ�7�l�0���&O��:��Q�[d�ΰ�a��6��E.=~�ɾ/��F�������L/��#&M����3&Kǆ��TbR&{�(��f14`��Kk�|���ނ�� (������inUh�6��B>���b�~�>��=��5ҽ�ƙp����:�pi1��][�'�Hjz�����k~հ�u�'���N��+��P�3�x<7��k� L�����w�N�1��a�jfW1�*l��!Cd%0�9X<�R�l^(f�kSh�[�9HC���	
�?��y󣏖5C7n$����C���pN��3�4\�:	[���N�ZSբ'��[[WH��.�O��
�E%�0e&����5�6�������e���Aj��M���@\�T�FS�d<�Is1H+&��`��y'ο���Sgj��hg����@d���!-�Q
�%�ң�G��������h�������X�>��W`:U��77���F�^ 3��	�� �i@i'�2������Y�#�e��MmR����õ�.'�~WN�6i)=	u�[TK*��H�����ź�T4�,��=f�]���h�)}�z�I��)h�ϛ��X�L[ Cd|!����b|.�1� 6٠ۅ���8+���h�	��~z��l����O=�H�d��]I�v������vʪ�G�ޯ/�BV�&�/����eK�*�i�'0|���*��.���O6�F��_�]E�ܶZ�!���P�	s2*�^P�
#ÿ����&x��Y	���O�`���;S	'#`���ֱ+�ԕΕ*P��t�{Z��;�v�y/",w��ӗr��o$�}���_g;ך��A=s� �g�E�|�¥=0�&&2&8�`�	�Y��84p��m-�	px�dmUM�OZK7,}H�����cwkR�F���Qte��b��y�Z�^P��0,�L�ޔ�2W?z4
2!��
ȮC�lv�'��L��EE�(���1�Q�]�7L}j���3-5�e(�W:K��,�詭���Q/A:]�;j�~��<�q�`<����z����M�����͟� #���bJ�d�
��[��E�
a��Z2`"�\\0�\I��C�m��R	��=p��-��S��;�G�KL0���+#2X�N��YW�I�x�i�����ހh�I!ȼ	?8�؊�D2Zd��/8��J:車�0�� 2S�p%<���z;J�,:�{]){TU=�����jW��wi��	ۭ�^t{h��q����(h�	rizd6�¶2#0A� *2��@�⇱7m�"/����<.�?���O��h|��\$��5�{	���=z��]d*k��r������^����Up!�Z���a�m�|��`��z�?�l[\����b�*�8-ePĆY� h�v☃����Dtp��Kvӆ�`bvܬ19�L����p%b(c�hf�������f��[�.��`��_��=u:/��>J�e����}ޯ���ϲj�{�2��^��ĉ�i�bm3�0�rL���=O�S?@�����3��Q����B�f'Z�=C���ul]��VK`>�w�2c���wU����c��~� �([���ٙ-
�-?��s���/*��,=J��������7>:>�8���!0ש-,����ywm�<d.'�wW��h��ri8�t����%��'�� ^��e���|����=}����Ң�c�E�HŚ� �>���=�=+�GN$�6V0`�o�F���r�Q�>��f�
<��6<5h����OݩqeRP�ٔ<E�_����$H ����պ�$SP��F~J+u��J�����GE8DYo{y�h��Iz��?�ڙN��A`���
�\��(�jh�W��A���̇�vh�Tm3�$N���FǧG�b�$�::�����`���-�L���?���=���.�z�v4����Q�� �0WJ�8rD�&4�h���	��	�F3��r<��xj/5. 0�OS�z��uvn�磻l���4[ʻ�fJ�c-�e��\.�#-���D֠�n�8#���8~��3I֖k(�����Hf�%c��x�b��|�I3�@���!v�� ���{�_�}�Vt���2���)�;aO\�I��:P�l�c`����@T�	��a��Ep��κJ�����,��75�v%β��g�Q�X��d2h�L>^hE�h[x(���Cxm��9*�ưΤ�$%�a0��:�ؑ#�	x��Nf15.S9:�蟜��O�i�]t�������ш�����bS�K�3�]�Rl���eVgkk��YT��E������������1h��#1�s�����S?�ֱ6�]��4�Z�wvv��ߎ}@\>�K=�2���$���9N���;;�p�y��;@�e����Bi����@�۴�7���,�'�
���Z>>C+yֲ:]����P���Rnvu,Ǝ1E���bj~���o �lΓg�a霼��V�k��L�ؕR\@�+� ̸*��#�EU�u�C���Zy*ۡ?�V7��E=]~�rxW�%�5:�,Њ`[�\䕇X�db"��w]0�I��y\�D�d���T�O+\�x�J��
glp�QL]�FBP�.G"��G�~�M�l�>enDX�Zզ�A�Ѻ���"��}��#e���ۊ�u%�>[ ^�9�(9��Y��|G���p8.�Cb&cc�aULEy���p&$y)||>�ԩ`�
L��=Yͷ�q����}��Mv��v��x���}��}�e��6Kv/���m��	gST]rV��-�s���j��'�'1XB���쾎$f<畉��ip1c/@�}Yೖ��X�sI!��ѫ����xJD��x��9�$%(��Xxsa_�;`�i#a�nw=T�f�n�����X�롆9##	8&��L�B���}}.�iX4���A�LH�^^P5�@�|�_�V�s���(Xj<L)�O��0�-Uw`ߛon��ھ'�v�w74-����Y/a�\V��_n7�}D������w$�[�=��'	�yB�>�"�$�E>K`�����W��+�N��I�����X\ˁ`��������G[UՂ���_k�����a�1g���_ZvF1��+�^��IW⯞�o�G��|� ��A}`c�Ԣo�\2��;M
I��]B=�B[+�ח#lp¼ �Ax^��@�U�V!��lLU��#���(��V��F�,mﭟa��{�l��`XQ�c��'f�f������u	\
�H��=f�&J���gx�C�	9��������.|���wiܲ4jU���V��*�L1L��:�)�kjj�P����T,Р
q0d���{�#�J @`H�q��"���QѠ�~���۟����M8HE\Zl�ǲk�yf���Q�Lvw��D�˜�ֺ��B]A~��b�7���N��Hk�����U 4�8;!Q�8��a4�`1���0P^�b3�S1�$I�Ksbpy�%׺M6��D�K�E%�(nH/GW\�~�M������r2��c�(�B�Pl�"������K B<��$%IH(�_�>̈|�Cޑ2��}{3�`Dr8$��,9U
��%zxD�R���&E�nw�$��3JPY��Ip�DVct�!�{٭��xI�k/�-T�
!����Q�����b��2�������H�=�~�={�0.Sa�C��z[�$�[Y��s+s]=]u�fE�5>��,��oxs���d���#�z��V%M� p��aɒ&��E��,�.Ю��ޣv��h�.'���"�}���lOn3���;'^}���)�p�<s������f@�t�d]�MM4N�s��KAle/�����{�Sa@���,�����Hh��d8I���2W.L����D�Wl�Y��B_�Ő�d�������n�N��z�nPZ�G8)yE���N��ˇ߾�ï��G�#W�Q�]L�(�h���%'%��.�/��I,���X��T���v��S�c����CF,�����VZ�D0,�A��b��V]o�{r46�F-�B����zhpr��
Fq��g�9+@��ᘜ�e)%�p3���Kw���~gc c�Qqg�������W^�3��FS�Ny�T�7Q�R)��v�2(qmm�'><ڻ<���=BF��bTy50r!�20��q�$[F�Pʬ^�������SWkn;\�20�a��os��V�O�:�χ�=���ܯ�bA)��fxtt��Dǂ˵�P$2R��Y�6�a�E�W�� �0Ҧ�4F�`�v;����91S�ҵŎ����g ���e4��A��a�a��s�"x
������F���yM/V��|���N���¹����}w���c�R%0�{%�#��	r �K��>��OD �>J`I�30HE�߼�yL�e�u;DEa� uZ���B<VZ�pzթ����&�`bL4[��UMZ�f�RS�pkBu4&�c��@�m2A�a��yߗԨ�|3��z��yߟ�5��6oޜ��ե�62����}{�D�xx�1�rֈ�xІ���	6�E�/Z.��[x�r�L���.80�5�T���xq���Pۺ_$L�X���	��ʤ��ڀĶ@���DwZ;����@����R�CHH+�7���q�*A��P�zR��Ѐ�����@w�U�<r��m1P���~@�0B5�&���1�k�t	����dػ:���	��ɣveXۯG�{��y(�ק�p��{`�6� *�H%X�9���N��ռ_��i�j��x�-��=�}�������R� :U���.7jF�n���?�쌼B"�Ko���gӒ�#���6������#G��~���`�vL'G��P����s��=2`��4�f���o~m��ڥ�m�(1��$�UѺQ�c���X�N?�	�s�#2��-m�\:ڞ�<��'/}~�*��/ ����p���xҧ���`��sT�ѪiH��ӹۿ��vj��"q�%�<�0�E�؜�#�$�4�u�����fT!�"���u55Z�`z{����n�6�ݓ����N�=�;� )v�|q��k10�����$��Ź���c.{ԗΖ����ul,͈ؐ�>�v�X\_�v8�?�1i�_A�Ki,��ZE^Q�j�2�nDxSzR{� ` �H`�)=);�bv{���t�@���԰&0`M"?pw�V�+��6���tk^��&�𨚰��S�wy�T��n��%�˘m3_L|؊p�Á����;�:�b2�N̦j��Q��{������ʷ�v)�xR�Dc�Ӟ�R�}#>^��n{۷4�a���=�M����[郣��=ϊfؚ�L���"�,a���W~A�XM�/������膔|��?>�N��{Cv�:�
�3��O�Ä���Ѫ����[�n�$�����%ף��7��A&��8|��Ż7�2�/���'b��\}��l	Xue�7
�ŉ�̏�����H%%۷}49Q��+�t	m2��ޝ�e��}j���VB|�����-�G��C�g---�\/C�'����)��D�o~��NY1��������Q��+g���ݻ��A%�~�
7��ծ�|�D���p�_�i����OD<�E�e��ȁc_��&�Z�-Mظ'Ĥ����!�dp��Cjj�F[����c�S2�L
��(�LxJ��x�v��G�����|X<�+���r�S�\\�X��	/�1_[�O����i��s�RC�޽��X��ܣG�\��͏�e�:S�%�Q���ŪQ����+22�BL���rX��;�(�Kss���X�8��L��M�r_ܣFp�g��+k2k��Wp�R�.�ӄe���xT�Tq�95=0�Ê�=�a汑��n6y33#�-^��_jXy�xM���T��Ҳ���)KJ
���p���m�h/)����q1��4~e��v��r.��x_��>��p��#���0v�������߻��_"7�`olq���q�X~�+�`D�dx������d4V
2d2 <�(t���ぅ�`�*�aBQx�ޒ��	"L���F���`��<\��s������{���  L�躄�-t�r_ǫ��2<���Ł��ڱC��ɯ�I�&�F�uZy*�`��U2`²k~���P�4s
������8筄	�CO��w"������+�͘[�<\(:��3-qA6`8�D�26�<�}l��H�fx����ïɯdoh���*�2t-�8�vz�鶭-�Kd�GEX��/R��NT� �����橌ѻ33{{Q��y#��sr�d�+F�R`��FF8�%�:"w� \����922���ї�����:�*��rx��2z$�w1��n����FJj�׶��bB�R�Վ6��P�3�v{�2�VQ��mX���-V	.�:���0���W�]�7
w���םgճ�"tL��#�C������c �R�޺ȑ�>���,��ј��z.5�h�Ji�J��̹��Ja2 mB�i�Z��]VQ<>6�L��b�t}��-���bs|q��/f�z7\(8Ue�e�ER��dH'`.71�B�� �,fd�����K�/���Ժ���7&��բ�q��|;l��lV�X��SZ��]�1F��KKC��~�*����Y�S�,ś������Y���a�tr���g�dJ�8�*��-�azM�]"SwbYf�j���B3x��P�::��
&�(c�Thi����{0�@��z�SOd�N"�m�ԨЃ CU��A��2�ְr��PV����0.�hL�`-[KMƵ�}xX?N���*����$T�3�~��!f�c�ptq�ad��D��R_��&,���ESNO�1X8u�Ֆ�NF�0a:/,
nTQn��=e���؍@I�=^\<;��0�S6y9�Ʌ������H�؈���W�{�0[�i�pTP˄�C��2��z�=}���d.��I��1� 4���P���0�TZ �dlzg��Uf��?hW*�,�4�\�*�KC��X��8��x��𣣅:�*n��R~�2[[J�\XZDFF�ge��	cc���H^FAw���_��^N�:�3y̆��j�&�҃��U���"�+�(���#ZBQ�������CiE��8��O�Sy�n������-R�'�[<`̪<�{��c��[g����L]i����C������[�%_��`LXZ��$���j�y�H����rRXxe�i�̂yN��R
0HHeee�ك@C1�U4�Mh`��7�����k�#YS���� u�ݲ��gnܘ�9h�5k�3���$KOOG��Sl��m�wQ�h{�	P��I��`�;������ь�Xn�1Bk<�� 8�A�=�
�F�HK��+��i `����yq��;��#����ޙ�Myf��ڜ����5l>S%�����^!U�)�%�e�Ķ�L&a4��:��E�� ��r(��o�[T�X��ۯ0D)A&� �4fgg�R�dPӈe6wPFK3�O��dv��e=�������]��]Z��C�J}�(*/�=�Bu�&�No6�Ƅ�S�V������c6ٱ<2���py��\**J-�X-N̞df#G�� �b����,ɂ��Z�k2���+f!�J`埐_�v�b�$���*L�|���鳧��I�;�s�:�V-B%M��x�6�h�R(?ӆ}gr��2�o�r��		��xP�@����ܙ�H�� T�2���h����4��!Qjqy��00��!MJ��� �#�P5���L��,Z��`�`D%C��+�Swee���ˀ�J���ь��F��q��&C�T���Gx��:�o�J��'���*�g�յl�=�]��>��,�p�$&K�f�����e���S�mFo�6����g���]��gs�#��v��.L�����R�r!KU>��
m�L<�ߌj���`������P֠�+`U�	B�?�;<����Ŀ�H̒��g�2��&&
ųHԡ�"� #-�B� �`�!6M#��t�/7��j��%���#�������>_�f�k
��N.جZ���pT��tx��0e(��� d����C �͙лp)�"�0&���`��	��o���x�g�^���t�Hd.L'"�X�����N=�jB�.Ą*��If	�	q?2K�}8�%�͌9d��`�B���%0��܇����^�ɫ�?�A=Y��7����QW�;�¿�)ɑ�΁Xuw�=<r�����TXj��7�
q��
\�V�l�T�u��q]��&��\߿ ��=N���ػ�O��_��NP5��1��Wb̏�Ym	�b��$W�`R�fo]������HO�ܘ�î6-��2�'�̀��A�4�#G�R�4���n�w��Q����My��-K�j�A��(+�����7�jd$�B��$�A��!��,֑������u	K+���Y���8A���WEњ��م$wR�l6��{T┘A����������<3hUɬ��`�k+�v���[���7}
aW�&Tѵ#�i�ԍ?VF'�+�i��s�g8Q�C<�f��lqY&��Lq۹�n�&c�V��dVZj6]�� �N�����4O��L��Z�d��L��n�Z���I/���m�:q1Dg�⌈0܉8������q�!.�xWؓe�Z!��,�;�f#ǙJ�T�X��s<-\Q�����������w0.l3�m��i�N�p*�����W'^GPl�������.��,F�!^�CST�es���������&�p���i������T�)��:��C|c��/�B���6�.ސ��勶ذ���4�B0,r���.wW�659ٌHK<�3��
0^2$���F�°����**x�}ĥƪ��
Gs��)Ũ��"�!a-{����%d�:���c�>����R���o�j�JX\��R�� 0��l�	!���S5ͮ"��n���@���	�W��Cꏴ�`T���t�;i�]�u[���C��<��p�	KMN����;^����vf"��y�&Ȑ�ˌ���L��x��x*/��C+��Tw�} ��������δ��jZh�'��s�}���K�"wᗌF���	�GĊ�e�wXP�>��w�̫�	�ě�k�E�Uu&�M�%.�^T�>Q'��Vp=�:9%"��B@7%tD���=F��>�cD�xw���yy�0���g&�#p�j�_��W�s�wͻ�
��S9�*>�j�z�_���^ǧ����w�L���Z�?$[!*�5��j'��9Y���?�L��.�`��4 3��^���!�P�3��Mǘ����C��H���&C`Vr��W�9|��Ϭ�f��>c�
I/���7\���g�ߒ�>�cG��E�w`@�q���ÅerChFc/��տ����2�}�M�e��cO��radv�|f} �,
|�d��iup*��E��vO��V��B�hqc��?��^��H�P�S���*A��!ÖN]��Ɵ���80P�I��''\��t�*	`d+���yP0�b&(�TS��\�#ͻ�w5�v�ֲ%��f��J.,��(�@	}}Yg��0�i�=��4U6�Q
�B-��&��Yl�`f?���044�2&�8j���Vma@`
����к<2��E�%�t�w�y=ט��I,Q#�Ǭ�⏑�������fk%�a<Ƃ�;3��,,��Zt 2(�!E4 ��02$�Z�rS�n��/:x+��+�rk^�~����MOhi��a�,���k�e���wN��薹g�^��C��������`�X�V�ШIk��p4c'�bv޺ŉ��I���锕`��.��4ό$M��E���!|Ν�-1<����_��TΜQ�m5�_�?��;�`"c����(;���J�ah�ц|��)����c�{��k��EΤZ�Z���H���J���0.�4�bd�L�H̡�w?n�`8����"Ψ��Q\�c(#.�4�����R���䜢�����?�``�|�3�ƕ�L�?6<6�!3�6���Q`�q����݃�H����(�"�%������E�i��=�Tg�$H�� ͵�j/��j7FD�L�WsF[* X�de%��ӻ'''�y�l������X���@+�����!z$q!-�"�cD�-@Ϭ[����9���Ӈ�ݕ���^������ݰ���0��}���<9��e��ܓb��3�<#.���[WoY�g".����Ya0���h /���+� �n۹1 ���w<�󉧶E6D���:����GD�N����H�#��<y򧓤߻�V�br��Y�����?�;ߐ��8������������Z6�-I�F�L�u�-�P����A��	�����
�AzIPO��m���n�@��f2��0�#�?����ss9�=p�7�Q���|>����oZA�ˇO~tE_)0*���|��]��%����}{[�ҥ~ľ�-t�t��S��g��ڋw�w������?|��Go<x�#������f	�L6��d���z�:ܯ��/��u}�i�
��?��W��?�~G��˓˫P~{�h�Ƣ�����ųgm���&�T�?�����ۇ>���c��I
�2;��0�kD�N��Z�t:ujP�|�}�^ڗ�jr30ad���v���R�^ ��&��
+��^S�Xe�w/�v�>����΢��\�n�������;����̈́��h��S�N(e�i]߹*v�Q���q�pz�+��}��3�=�%�Q0	�fusη.9����,Z�p���k��ٹlP ����OT��_�R10��(��y�UB��`������u��ݷ�]��DWㅆ���o�.��|����u�A�⣯,�76V����ɸA�k8$�r��+���uq��ɡ����� ��.�V�𔭒���,���?�����r[��{K�j;�^YZ��}���ӠC�3o<l�M����@�\�2���n2. �eg��,`���A�w�P�̒�����Ƚ��lkw6|�ص\�8��A�uϽ^`�I����bd ��e��yP�P� {q����e��ڵp#���l&˪9�;!`k�0^�_���^iz	V��:5���/�/zH6Vs����%x�Ud���6�]��"=`H��EćRn��\.���R����m�ҷ�����w��6	��"��t��3	��ƣ&ø�O6��墵��.��	]};6jO�N^���$� �jk�IϝI0�γ���(&�p�EZ���*J�$���s.c�R
`����z�	V0qZ��U�T�|��$)_�Hݨ˩��s��J�EI�U�[��)`�o� 3|Ys��bY�Hݼ��(U�̬Í�O��v���Y
N�H�H������Uxaq�Z��@��j~��7�a�����q�F|��Fc��h6��"��T
&�����,�i�~7��gO�@m_,u��RRp#�|���}�j6G�F����������kɱ��p10�\�i�șox-y5��pZW+�b))᠛{���<�P0��y��p:�0S �6Ct��o;�`�6��N:��(
DFQ}���O+F�"�q2���.�����o��fi)�G�嬉�\L*��u��H�#����?���2-�G(��d��\��'�X �6���K���9kї�����NXV���<��(:��7*�#Yz\�_"�H�����P.s9�_w���@:�d�^��<+ź��Ӝ�ÑF�FD���\_���e����2 ��}����ұ�¾��.YRX�+C�x�����y3���OC������ܭG�)C�`r�sg'��jK��i����l3��w�͇N��t6�U�E�HZD3�#~��$�l��ܒ�ח�����pF�p*`�E�3s�bA`ag�Sh��Jo�Z͔�u����H#&��􎾓���A2]7s���X5	`��t��8{z'�]�r����c9��)^f/ ��I�p�L�,,徍���d!�_��@�b�	FU/�W/7���)Fq����GlGR{��SđZ��N�N������sP��IL��X���&'�ig8�I"Z&h"O��F�FF�h�%d�[\F�SV���Y%G6��ɞ0��%��$I.�n���ù@j�U��#T�L�I/3;nB&,g�v��N�
�P.��(h�����5�9� �2Eɴ�����!�)��e뜯��:S�0��D[XKQ�=��.��j�"��&{��x����2����������y6V֕�4+�p�:2m����̑�� #ڍ�78��=�V C�������2x�Fc�Ͻ���<v�D�B�Ω92!ko�JAb+�����׫����C(JR2YE�|��*H+�b�&>��Y/�����K��#�bxj0�A-�r4�0�e2�6P�,1���L�*}IcS�6ZT��,(d�3>�u��kL�` &
I�r?��ُ��0�l��B��
M��5���r�UWX��n���{�w��a0�M�4o��k3����ٜHPgB�T@����t����P0�2�A�
#�>E0J	����� �ʡ�s��r$K�H����@0��ͱ���N�r�Sm�ݢt��0(�vt4� �W6�{P	�7&���0cu]u�eeT�Zc���T0��� x�up�2�e��{0R`�V/i8;��'1���x�7Ǡ��ŀ�AU!^P챸#,��X����u�\�0-�-N�Fl<rO��ޓؠ�*ocU���b����b�9m6���V|���B�8¯`��ٔDͥ^���n�H�~��J0t��B�T
X�SN&�����u����Wj�(�"r("Q���A�c���¥�nt�Y9������*�;�	6�@�e���u�������#���ˡ�LNlb0 ���ݰ{�c`P���u؋ CaT�Rz@���Ai��CE���*-v��B��� �Sϲ��F'(���z���g�U�0���҅� ���0�4�>�Q��{]^���\L�p���h<��L��2� �G�Lǫ�r�����d	iu&��4R>��d�3n��6"U�a�<��t L*
.A����6?��6�cgL�D�0^��c���L C&���2�D���<�(�Do��gG*�r�D�p���ĻE4H�01ʝ��9|m��0��m�A n�|8e�t ��B���`$��<�)d<��̆7�#O�+-��T�C�ATL���u&�A�F#�x��;��i��PgڧE�I�x�`¢IW��U�RR=.Le�#�����?$�ݸ��<m�VY.�s�Ņmj.�����U�n4�M,�%vd�@F���?������p5����`f�ࢸ�N�c�h�^�b�29��h��brJv��{�f��t��k-֔j��6oQo�`�QY�CU�Ӎ]f
�r� S����g�?u0x��w5�ƴ��;_��
��� `�HT]��<��o�J��^��RP���ؘ[��F�kb	tO�l����E0��X\Fn=\!2������$�R0E�̭fs��"��@��������h����P���J$�ckh hB�ɴ�6S�Z�9To�l�V4�_R��g��I�M%s�6��;�*�k�L��%�lV��S��h��h���;��A�y��?9�D�i�7����hT�S�a����k��+�z`4�� ��L��2Bo������h<�n=<�"�,
� Ev�K,&�Q25���他�qvË�¬���G(F�``�fө���.@&�U��HC-�.\���V*'��� S�P�'\b!����q4�f����%Рd����r��d�hy�|Ge�L��h/�ĝ�������;D���)�њ$�(Z�̰��!9
0�-V����+<����ˊ�P�m���VIJ�"���k�`�W_AI}w	w���f�y�����9�S/�8v��~��2���Ӳ��X|�P�@��$y�`�aDqH��p��A�¥��B���+73C�2/_Їݧ���䬋�#�["�)u&e*�.�9��o 'F����Jw��O�Aſ/��P�U�
�EW�LJ\��q�E�)��o7�����ث�B�L����h0��+ᵮ��SA�`���g����ھ}-7��q,�̅ݯ�����*(᧡���Jk��nY$����"dH"��v������Ar��Ri����#�A\��[�X�n�[vb�VΉ�b�P	׀9q�k��p�^�Q�m*Q:p��&+�ʂ��M���1[x�v/��"T������͉��Nw�@%*!�N�
a�R2�{~�`}1`h�VD�6m�C ��1���.�>���"�w�$2�4�m���5F�Y�=C��6�������m4���L�_/�T�$9�x��d��VgmT}��H:F;9�;�q4��%��Β�T���-����	/_I���=5�.+���aHcl�?�Z7�]� �D�"�M�ջ��4�
#M �%쳥6;8�p�À	�y0��aK��{��ؤ&Sg2ҙ���eB\3@������v��`z�k^&<��^QJS)MU��x�ܣ�MQ�K��hq�4�����m�	�M�iL�c�u{��?���"��F��b�8��/y�H��C��XyiO�3�����j.�Y�ѐ%��hh�vg ���Y��E�q3��EV��h3�����Hf+QB�O4���ћ��Pd��pƑ�}�ʺ��d.�2�F?ֆ�U�_�A]_�iX�Id�F��c�k��w�� ���$���1��r��8ޟ���=�\V�h��-����.gԢ�!�K�t{㻭�*L9F5S���/�7;ù#�N`�gt� M���k�lOJ�̥M�a�W\6�]�\X�v����+�"�=W��yդ��,�]��d4�\�ϰFj�	���ܹw�(�l/I��]�JjN��K����WE��B>��&24��,�����Ӣ�jy�w �>�V"8�G�݀u��d�X���F���'`�n�:G�_�!k��E4�]N��p�A��`V�=�?%#Mb�i��VE�1�i�F�lx���c �w]��dR�Eq���[����(����H����&�`l�X"�G�=�4�1'�t�>�#��n~�D�4�ڭW��Bs�TF�v4������V�@*�L<b���b��~�J%7>��t6�r��kh�4.��o�%�G�����5p�B�{��Lt�O]6�d%�-���Z�qu<��h�.�M�6�to�`3�Vz�
�<25ϝ�.��%�%?h���b'<�gKi�POfib�L��U�b��dRF�q��I+~R�7.$]+s��P�݄���Zww���5��
>���H޲���n3�R���Ư(�]���Vdh��W}����\Rza�`�$��t��/ޣ�FOrx�T�<�������<%%q1{�n��~́�v���%���/�G7��aX��:�K����<����v��7�V󘸐��V`D�fF�|���y�4&�d���&Zގ��d�̩��A)}=�)���u&�Oτ��F�Y.s@8j�����*��(ӣ���L�0�R"�:z,_qa��Hpƫ������/f˺��.�N����LyV�**Oy��₀^�ɍ� G*�r��g3yh5��b�.r����x�p��m�+撰(�d��`}T`�y��c����I2�l�1~72m	C���.��	13�|����shů0���M|����R��L!f�іG�����஥(X��F�A���}�ǊsW�0�.b��-|����ڛ����mS�]��RLE��_��bD�
�Y$c2�z�p=�9�,�/��Y,�����d�Q����'�t$J��b�a�ۭV�e7��w��f����)�DNj������h3����)�*��uPa4Ƥr��Fa�[����+��P0\�x[���jK`� 0�re�T|�u����wm��t�x�����h�$?MQdz��ʄ ��#�s�\�'�L�pSb��` p��FP��`a*������ۛ���z�4�A�D0�}���˧�l�Mk��z5�=�noն+�ҽ ����2�������4���oN�T�`��!|��2�Ȅްck�r��њb�y/����Ͽ��|�9�a�_J���n�D8�=����=|�lб�K*z�x�Ɍ^�ƨ��W�EQ�H6�    IEND�B`��yӿ&�|K�)7Ē�����   GBMB