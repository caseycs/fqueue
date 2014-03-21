<?php
namespace FQueue;

class JobRow
{
    const RESULT_SUCCESS = 1;
    const RESULT_FAIL = 2;
    const RESULT_ERROR = 3;

    private $class, $id;
    private $params = array();

    public function __construct($class = null, array $params = array(), $id = null)
    {
        $this->class = $class;
        $this->params = $params;
        $this->id = $id;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }
}

