<?php

include("simpleApi/simpleapi.class.php");

class templates extends \SimpleApi\DefaultEntity
{
    public function renderDoc()
    {
        $doc = parent::renderDoc();
        $doc["description"]="A list of all templates on the system";
        return $doc;
    }

    public function main()
    {
        $restCrud = new \SimpleApi\crud($this);
        $restCrud->setOverviewFields("id,name,owner");
        $restCrud->setEditFields("name,nameserver,serial");
        $restCrud->setTable("templates")->render();
        print "MAIN";
    }
}


class records extends \SimpleApi\DefaultEntity
{
    public function renderDoc()
    {
        $doc = parent::renderDoc();
        $doc["description"]="A list of all records on the system";
        return $doc;
    }

    public function main()
    {
        $restCrud = new \SimpleApi\crud($this);
        $restCrud->setTable("records")->render();
    }
}


$api = new \SimpleApi\SimpleAPI();
$api->db->connect("localhost","root","trojka69","leandns");

$api->urlPrefix("/api");
$api->registerEntity('/templates/*', new templates()); // Get
$api->registerEntity('/records/*', new records()); // Get

#$api->registerEntity("/", new oversigt());





$api->run();
