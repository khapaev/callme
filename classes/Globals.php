<?php

class Globals
{

    static private $instance = null;
    public $calls = [];
    public $uniqueids = [];
    public $FullFnameUrls = [];
    public $intNums = [];
    public $Durations = [];
    public $Dispositions = [];

    static public function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }
    private function __clone()
    {
    }
    private function __wakeup()
    {
    }
}
