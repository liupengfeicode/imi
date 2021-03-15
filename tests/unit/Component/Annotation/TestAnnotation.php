<?php

declare(strict_types=1);

namespace Imi\Test\Component\Annotation;

use Imi\Aop\Annotation\Inject;
use Imi\Bean\Annotation\Bean;
use Imi\Enum\Annotation\EnumItem;
use Imi\Server\Http\Route\Annotation\Route;

/**
 * @Bean("TestAnnotation")
 */
class TestAnnotation
{
    /**
     * @EnumItem("test")
     */
    const CONST_VALUE = 1;

    /**
     * @Inject("ErrorLog")
     *
     * @var \Imi\Log\ErrorLog
     */
    protected $errorLog;

    /**
     * @Route("/testAnnotation")
     *
     * @return void
     */
    public function test(): void
    {
    }
}