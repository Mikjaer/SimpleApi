<?php
namespace {
    class SimpleApiFactory
    {
        const version = "4.0";
        protected static $instance;

        public static function registerInstance(\SimpleApi\SimpleApi $instance)
        {
            self::$instance=$instance;
        }

        public static function getInstance()
        {
            return self::$instance;
        }
    }
}
?>