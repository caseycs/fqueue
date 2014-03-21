<?php
namespace FQueue;

class JobRow
{
    const RESULT_SUCCESS = 1;
    const RESULT_FAIL = 2;
    const RESULT_ERROR = 3;

    private $class, $id;
    private $params = array();

    public function getClass()
    {
        return $this->class;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getId()
    {
        return $this->id;
    }
}

