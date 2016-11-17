<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 15/10/2016
 * Time: 02.52
 */
namespace SimpleApi;
include("factory.class.php");
include("mysql.class.php");
include("crud.class.php");

class response
{
    protected $headers;
    protected $content = null;
    protected $statusCode;
    protected $statusText;
    public function __construct()
    {
        $this->returnCode("200");
    }
    public function header($header)
    {
        $headers[]=$header;

        return $this;
    }
    public function returnText($returnText)
    {
        $this->statusText = $returnText;
        return $this;
    }
    public function returnCode($returnCode)
    {
        $err["200"] = "OK"; // Default
        $err["201"] = "Created"; // Default
        $err["202"] = "Accepted"; // Default

        $err["400"] = "Bad Request";
        $err["401"] = "Unauthorized";
        $err["403"] = "Forbidden";
        $err["404"] = "Not Found";
        $err["405"] = "Method Not Allowed";
        $err["410"] = "Gone";
        $err["415"] = "Unsupported Media Type";
        $err["422"] = "Unprocessable Entity";
        $err["429"] = "To Many Requests";

        if (isset($err[$returnCode]))
        {
            $this->statusCode = $returnCode;
            $this->statusText = $err[$returnCode];
        }
        else{
            $this->statusCode = $returnCode;
            $this->statusText = "";
        }

        return $this;
    }
    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    public function content($content)
    {
        if (is_array($content))
            $this->content = json_encode($content);
        elseif ($this->isJson($content))
            $this->content = $content;
        else
            $this->content = json_encode(array("text"=>"$content"));

        return $this;
    }
    public function send()
    {
        $sapi_type = php_sapi_name();
        if (substr($sapi_type, 0, 3) == 'cgi')
            header("Status: " . $this->statusCode . " " . $this->statusText);
        else
            header("HTTP/1.1 " . $this->statusCode . " " . $this->statusText);

        header("Content-type: application/json");

        if ($this->content == null)
        {
            $ret["statusCode"] = $this->statusCode;
            $ret["statusText"] = $this->statusText;

            $this->content = json_encode($ret);
        }
        print $this->content;
        die();
    }
}

class SimpleAPI
{
    public $entities;
    public $prefix = null;

    public function __construct()
    {
        $this->db = new Mysql();
        \SimpleApiFactory::registerInstance($this);
    }

    public function urlPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
    public function registerEntity($path, $module)
    {
        $this->entities[$this->addSlashes($path)] = $module;
        $module->setPath($path);
        $module->setParent($this);
    }
    private function chopQueryString($str)
    {
        if (strpos($str,"?"))
            return substr($str,0,strpos($str,"?"));
        else
            return $str;
    }
    private function stripSlashes($str)
    {
        $s = $str;
        if ($s[0] == "/")
            $s = substr($s,1);

        if ($s[strlen($s)-1] == "/")
            $s = substr($s,0,strlen($s)-1);

        return $s;
    }
    private function addSlashes($str)
    {
        $a = $str;

        if ($a == "")
            return "";

        if ($a[0]!="/")
            $a = "/".$a;

        if ($a[strlen($a)-1]!="/")
            $a = $a."/";

        return $a;
    }
    private function chopTokken($uri)
    {
        return $this->addSlashes(substr($this->stripSlashes($uri),0,strrpos($this->stripSlashes($uri),"/")));
    }
    private function call($entity)
    {
        if (!$this->entities[$entity] instanceof DefaultEntity)
        {
            $this->error(501,"Given entity $entity Not a valid SimpleAPI Entity");
        }
        else
        {
            $e = $this->entities[$entity];

            // Initialize module

            $allowed_methods = array("POST","PUT","GET","DELETE");

            $this->request = json_decode(file_get_contents("php://input"));

            if (in_array($_SERVER["REQUEST_METHOD"],$allowed_methods))
                $e->method = $_SERVER["REQUEST_METHOD"];

            $e->uri = substr($_SERVER["REQUEST_URI"],strlen($this->prefix));

            // Figure out which methode to call

            if ((in_array($_SERVER["REQUEST_METHOD"],$allowed_methods))
                and (method_exists($e, $_SERVER["REQUEST_METHOD"])))
                    return $e->$_SERVER["REQUEST_METHOD"]($this);
            else
                return $e->main();
        }
    }
    function matchPath($a,$b)
    {
        if (strpos($a, "*")) {
            if (substr($a,0,strpos($a,"*")) == substr($b,0,strpos($a,"*")))
            {
                return substr($b,strpos($a,"*"))?:"*";
            }
            else
                return false;
        } else {

            $vars = array();

            $_a = preg_split("/\//", $a, NULL, PREG_SPLIT_NO_EMPTY);
            $_b = preg_split("/\//", $b, NULL, PREG_SPLIT_NO_EMPTY);


            foreach ($_a as $k => $v) {
                if ($_a[$k] != $_b[$k]) {
                    if ($v[0] != "{")
                        return false;
                    else {
                        $var = substr($v, 1, strlen($v) - 2);
                        $vars[$var] = $_b[$k];
                    }
                }
            }

            if (count($_a) != count($_b))
                return false;

            return $vars;

        }
    }
    public function run()
    {
        if (!isset($this->entities["/"]))
            $this->entities["/"] = new entityOverview($this);

        $uri = $this->addSlashes($this->chopQueryString($_SERVER["REQUEST_URI"]));
        if ($this->prefix != null)
            if (substr($uri,0,strlen($this->prefix))==$this->prefix)
                $uri = substr($uri,strlen($this->prefix));

        $uri = addSlashes($uri);
        foreach ($this->entities as $entityname=>$entity) {
            if ($entityname == $uri)
                return $this->call($entityname);

            if (is_array($urlVars = $this->matchPath($entityname, $uri))) {
                $entity->urlVars($urlVars);
                return $this->call($entityname);
            } else if (is_string($urlVars))
            {

                $entity->urlVars($urlVars);
                return $this->call($entityname);
            }
        }
        if ($this->entities["/"])       // Default
            return $this->call("/");

        return $this->noEntity();       // Fejl håndtering
    }
    protected function noEntity()
    {
        $this->error(404,"No entity found");
    }

    public function error($errorCode, $errorMessage)
    {
        $response = new response();
        $response->returnCode($errorCode)->send();

    }

}

class DefaultEntity
{
    protected $response;
    protected $payload;
    private $urlVars;
    public $db;
    public $parent;
    public $path;
    public $method;

    public function __construct()
    {
        $this->response = new response();
    }

    public function method()
    {
        return $this->method;
    }

    public function setParent($p)
    {
        $this->parent = $p;
        $this->db = $p->db;
    }
    public function setPath($p)
    {
        $this->path = $p;
    }

    public function uri($uri)
    {
        $this->uri = $uri;
    }

    public function urlVars($vars = NULL)
    {
        if ($vars == NULL)
            return $this->urlVars;
        else
            $this->urlVars = $vars;
    }

    public function renderDoc()
    {
        if ($this->path[strlen($this->path)-1] == "*")
            $doc["entity"] = substr($this->path,0,strlen($this->path)-1);
        else
            $doc["entity"] = $this->path;

        $doc["resourceUri"] = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"].substr($_SERVER["REQUEST_URI"],0,strlen($_SERVER["REQUEST_URI"])-1).$doc["entity"];
        $doc["description"] = "This should be some kind of description";

        return $doc;
    }

    public function main()
    {
        print "This is default main routine";
    }
}

class entityOverview extends \SimpleApi\DefaultEntity
{
    private $instance;

    public function __construct($instance)
    {
        $this->instance=$instance;
    }

    public function main()
    {
        foreach ($this->instance->entities as $k=>$e) {
            if ($k != "/") {
                    $_ = $e->renderDoc()["entity"];
                    $doc[$_]=$e->renderDoc();
                }
        }

        //$entity["records"] = array("entity"=>"templates","resourceUri"=>"http://backend.leandns.com/api/templates","description"=>"A list over templates");
        //$entity["nodes"] = array("entity"=>"templates","resourceUri"=>"http://backend.leandns.com/api/templates","description"=>"A list over templates");

        $response = new \SimpleApi\response();
        $response->content($doc)->send();
    }
}