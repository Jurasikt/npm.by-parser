<?php
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

}