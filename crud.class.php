<?php
namespace SimpleApi;
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 01/11/2016
 * Time: 19.14
 */
class crud
{
    private $table;
    public $db;
    public $entity;
    public $uid;
    public $rows;
    private $handlers;

    /* Setters */

    private $overviewfields = null;
    private $editfields = null;

    public function setOverviewFields($fields)
    {
        $this->overviewfields = $fields;
        return $this;
    }

    public function setEditFields($fields)
    {
        $this->editfields = $fields;
        return $this;
    }

    public function registerFieldHandler($field, $handler)
    {
        $this->handlers[$field] = $handler;
    }
    /* End Setters */

    /* Commons */
    public function choptrailingslash($str)
    {
        if ($str[strlen($str)-1]=="/")
        {
            return substr($str,0,strlen($str)-1);
        }
        else
            return $str;
    }
    /* End Commons */

    public function __construct($entity)
    {

        $instance = \SimpleApiFactory::getInstance();


        $this->db=\SimpleApiFactory::getInstance()->db;
        $this->prefix=\SimpleApiFactory::getInstance()->prefix;

        $this->e = $entity;
    }

    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }
    public function requestBody()
    {
        $requestBody = json_decode(file_get_contents('php://input'),true);
        return $requestBody;
    }
    public function updateRow($id,$body)
    {
        $changes = 0;
        #updateRow($id,$this->requestBody());
        foreach ($body as $key=>$value)
        {
            if (!$this->rows[$key])
                die("FATAL: Illegal field name $key");

            $oldvalue = $this->db->fetchValue("select $key from $this->table where `$this->uid` = '$id';");
            if ($oldvalue != $value)
            {
                $this->db->query("update $this->table set $key = '$value' where `$this->uid` = '$id';");
                $changes++;
            }

       //     print "V:".$value."\n";
         //   print "$key - $value\n";
        }
        $response = new response();
        $response->returnCode(202)->content(array("changes"=>$changes))->send();
    }
    public function rowExists($id)
    {
        if ($row = $this->db->fetchRow("select * from $this->table where `$this->uid`='$id';"))
            return true;
        else
            return false;
    }

    public function createRow()
    {
        foreach ($this->requestBody() as $key=>$value)
            if (!$this->rows[$key])
                die("FATAL: Illegal row name $key");

        foreach ($this->requestBody() as $key=>$value)
        {
            $keys[]=$key;
            $values[]=$value;
        }

        $this->db->query("insert into $this->table (`".implode("`,`",$keys)."`) values ('".implode("','",$values)."');");

        $id = $this->db->lastInsertID();

        $resourceUri=$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"].$this->choptrailingslash($_SERVER["REQUEST_URI"]);
        $resourceUri.="/".$id;

        $response = new response();
        $response->returnCode(202)->content(array("resourceUri"=>$resourceUri,"rowId"=>$id))->send();
    }

    public function deleteRow($id)
    {
        $response = new response();

        if ($this->rowExists($id))
        {
            $this->db->query("delete from $this->table where `$this->uid` = '$id'");
            $response->returnCode(202)->content(array("delete"=>"Done"))->send();
        } else {
            $response->returnCode(202)->content(array("delete"=>"Not found"))->send();
        }


        //
        }
    public function showRow($id)
    {
        $response = new response();
        $row = $this->db->fetchRow("select * from $this->table where `$this->uid`='$id';");

        if ($this->editfields != null)
        {
            $editfields = explode(",",$this->editfields);
            foreach ($editfields as $field)
            {
                $newrow[$field]=$row[$field];
            }
            $row = $newrow;
        }

        $response->returnCode(202)->content($row)->send();
    }

    public function listRows()
    {
        $response = new response();
        $rows = $this->db->fetchRows("select * from $this->table;");
        if ($this->overviewfields != null)
            $overviewFields = explode(",",$this->overviewfields);
        else
            $overviewFields = null;

        foreach ($rows as $rowid=>$row)
        {
            $_ = array();

            $rows[$rowid]["resourceUri"]=$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"].$this->choptrailingslash($_SERVER["REQUEST_URI"]);
            $rows[$rowid]["resourceUri"].="/".$row[$this->uid];

            foreach ($row as $fieldName=>$field)
            {
                if (($overviewFields != null) and ((!in_array($fieldName,$overviewFields))))
                {
                    unset($rows[$rowid][$fieldName]);
                }
            }
        }
        $response->returnCode(202)->content($rows)->send();
    }

    public function render()
    {
        $rows=$this->db->fetchRows("desc $this->table;");

        foreach ($rows as $row)
            $this->rows[$row["Field"]] = $row;

        foreach ($rows as $row)
            if (($row["Extra"] == "auto_increment") and ($row["Key"] == "PRI"))
                $this->uid = $row["Field"];

        foreach ($this->rows as $key=>$row)
            if (!$this->handlers[$key])
                $this->handlers[$key]=new DefaultHandler($this);

        $response = new response();

        if ($id = intval($this->e->urlVars()))
        {

            switch ($this->e->method())
            {
                case "GET";
                    $this->showRow($id);
                case "POST":
                    $this->updateRow($id,$this->requestBody());
                case "DELETE":
                    $this->deleteRow($id);
            }
        } elseif (($this->e->urlVars() == "*"))
        {
            switch ($this->e->method())
            {
                case "GET":
                    $this->listRows();
                case "POST":
                    $this->createRow();
                    //$response->returnCode(202)->content("Create new row")->send();
                    break;
            }

        }

        $response = new response();
        $response->returnCode(404)->returnText("Unknown action or resource")->send();

    }
}

class defaultHandler
{
    private $crud;

    public function __construct($crud)
    {
        $this->crud = $crud;
    }

    public function setValue($value)
    {
        return $value;
    }

    public function getValue()
    {}

    public function validate()
    {
        return true;
    }
}