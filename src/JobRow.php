<?php
namespace FQueue;

class JobRow
{
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL_TEMPORARY = 'fail_temporary';
    const STATUS_FAIL_PERMANENT = 'fail_permanent';
    const STATUS_FAIL_INIT = 'fail_init';
    const STATUS_RETURN_INVALID = 'return_invalid';
    const STATUS_ERROR = 'error';
    const STATUS_TIMEOUT = 'timeout';

    private $class;

    private $id;

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

