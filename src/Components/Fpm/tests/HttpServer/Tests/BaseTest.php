<?php

declare(strict_types=1);

namespace Imi\Fpm\Test\Web\Tests;

abstract class BaseTest extends \Imi\Fpm\Test\BaseTest
{
    /**
     * 请求主机.
     *
     * @var string
     */
    protected $host;

    /**
     * @param string $name
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->host = imiGetEnv('HTTP_SERVER_HOST', 'http://127.0.0.1:13000/');
    }
}