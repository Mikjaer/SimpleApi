<?php
namespace SimpleApi;
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 01/11/2016
 * Time: 19.18
 */

class Mysql
{
    protected $db;

    public function connect($hostname, $username, $password, $db)
    {
        $this->db = new \PDO('mysql:host=' . $hostname . ';dbname=' . $db . ';charset=utf8mb4', $username, $password);
    }

    public function fetchRows()
    {
        $args = func_get_args();

        if (count($args) == 0)
            return $this->instance->log->error("No SQL Query given");

        $stmt = $this->db->prepare($args[0]);

        if (count($args) == 1) {
            $stmt->execute();

            if ($stmt->errorCode() != 0)
                die("SQL Error: " . $stmt->errorInfo()[2]);
                //return $this->instance->log->jump()->error("SQL Error: " . $stmt->errorInfo()[2]);
        }

        if (count($args) > 1) {
            array_shift($args);
            $stmt->execute($args);
        }

        $ret = array();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ret[] = $row;
        }
        if (count($ret) > 0)
            return $ret;
        else
            return false;
    }

    public function fetchRow()
    {
        if ($array = forward_static_call_array(array($this, 'fetchRows'), func_get_args()))
            return array_shift($array);
        else
            return $array;
    }

    public function fetchValue()
    {
        if ($array = forward_static_call_array(array($this, 'fetchRow'), func_get_args()))
            return array_shift($array);
        else
            return $array;
    }

    public function query()
    {
        forward_static_call_array(array($this, 'fetchRow'), func_get_args());
    }

    public function lastInsertID()
    {
        return $this->db->lastInsertId();
    }
}