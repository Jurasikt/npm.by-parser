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

}