<?php

declare(strict_types=1);

namespace Imi\Aop\Annotation;

use Imi\Bean\Annotation\Base;
use Imi\Bean\Annotation\Parser;

/**
 * 过滤方法参数注解.
 *
 * @Annotation
 * @Target("METHOD")
 * @Parser("Imi\Bean\Parser\NullParser")
 */
class FilterArg extends Base
{
    /**
     * 参数名.
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * 过滤器.
     *
     * @var callable|null
     */
    public $filter = null;
}
