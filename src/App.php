<?php

declare(strict_types=1);

namespace Imi;

use Imi\Bean\Annotation\AnnotationManager;
use Imi\Bean\Container;
use Imi\Bean\ReflectionContainer;
use Imi\Bean\Scanner;
use Imi\Core\App\Contract\IApp;
use Imi\Core\App\Enum\LoadRuntimeResult;
use Imi\Event\Event;
use Imi\Server\ServerManager;
use Imi\Util\Composer;
use Imi\Util\Imi;
use Imi\Util\Text;

class App
{
    /**
     * 应用命名空间.
     *
     * @var string
     */
    private static string $namespace = '';

    /**
     * 容器.
     *
     * @var \Imi\Bean\Container
     */
    private static Container $container;

    /**
     * 框架是否已初始化.
     *
     * @var bool
     */
    private static bool $isInited = false;

    /**
     * 当前是否为调试模式.
     *
     * @var bool
     */
    private static bool $isDebug = false;

    /**
     * 运行时数据.
     *
     * @var RuntimeInfo
     */
    private static ?RuntimeInfo $runtimeInfo = null;

    /**
     * 是否协程服务器模式.
     *
     * @var bool
     */
    private static bool $isCoServer = false;

    /**
     * 上下文集合.
     *
     * @var array
     */
    private static array $context = [];

    /**
     * 只读上下文键名列表.
     *
     * @var string[]
     */
    private static array $contextReadonly = [];

    /**
     * imi 版本号.
     *
     * @var string
     */
    private static ?string $imiVersion = null;

    /**
     * App 实例对象
     *
     * @var \Imi\Core\App\Contract\IApp
     */
    private static IApp $app;

    private function __construct()
    {
    }

    /**
     * 框架服务运行入口.
     *
     * @param string $namespace 应用命名空间
     * @param string $app
     *
     * @return void
     */
    public static function run(string $namespace, string $app): void
    {
        /** @var \Imi\Core\App\Contract\IApp $appInstance */
        $appInstance = self::$app = new $app($namespace);
        self::initFramework($namespace);
        // 加载配置
        $appInstance->loadConfig();
        // 加载入口
        $appInstance->loadMain();
        Event::trigger('IMI.LOAD_CONFIG');
        // 加载运行时
        $result = $appInstance->loadRuntime();
        if (LoadRuntimeResult::NONE === $result)
        {
            // 扫描 imi 框架
            Scanner::scanImi();
        }
        if (!($result & LoadRuntimeResult::APP_LOADED))
        {
            // 扫描组件
            Scanner::scanVendor();
            // 扫描项目
            Scanner::scanApp();
        }
        // 初始化
        $appInstance->init();
        // 注册错误日志
        self::getBean('ErrorLog')->register();
        Event::trigger('IMI.APP_INIT');
        // 运行
        $appInstance->run();
    }

    /**
     * 框架初始化.
     *
     * @param string $namespace
     *
     * @return void
     */
    public static function initFramework(string $namespace)
    {
        \define('IMI_PATH', __DIR__);
        // 项目命名空间
        static::$namespace = $namespace;
        // 容器类
        static::$container = new Container();
        // 注解管理器初始化
        AnnotationManager::init();
        static::$isInited = true;
        Event::trigger('IMI.INITED');
    }

    /**
     * 创建协程服务器.
     *
     * @param string $name
     * @param int    $workerNum
     *
     * @return \Imi\Swoole\Server\CoServer
     */
    public static function createCoServer(string $name, int $workerNum): \Imi\Swoole\Server\CoServer
    {
        static::$isCoServer = true;
        $server = ServerManager::createCoServer($name, $workerNum);

        return $server;
    }

    /**
     * 是否协程服务器模式.
     *
     * @return bool
     */
    public static function isCoServer(): bool
    {
        return static::$isCoServer;
    }

    /**
     * 获取应用命名空间.
     *
     * @return string
     */
    public static function getNamespace(): string
    {
        return static::$namespace;
    }

    /**
     * 获取容器对象
     *
     * @return \Imi\Bean\Container
     */
    public static function getContainer(): Container
    {
        return static::$container;
    }

    /**
     * 获取Bean对象
     *
     * @param string $name
     * @param array  $params
     *
     * @return object
     */
    public static function getBean(string $name, ...$params): object
    {
        return static::$container->get($name, ...$params);
    }

    /**
     * 当前是否为调试模式.
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        return static::$isDebug;
    }

    /**
     * 开关调试模式.
     *
     * @param bool $isDebug
     *
     * @return void
     */
    public static function setDebug(bool $isDebug)
    {
        static::$isDebug = $isDebug;
    }

    /**
     * 框架是否已初始化.
     *
     * @return bool
     */
    public static function isInited(): bool
    {
        return static::$isInited;
    }

    /**
     * 获取运行时数据.
     *
     * @return RuntimeInfo
     */
    public static function getRuntimeInfo(): RuntimeInfo
    {
        if (null === static::$runtimeInfo)
        {
            return static::$runtimeInfo = new RuntimeInfo();
        }

        return static::$runtimeInfo;
    }

    /**
     * 获取应用上下文数据.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        return static::$context[$name] ?? $default;
    }

    /**
     * 设置应用上下文数据.
     *
     * @param string $name
     * @param mixed  $value
     * @param bool   $readonly
     *
     * @return void
     */
    public static function set(string $name, $value, bool $readonly = false)
    {
        if (isset(static::$contextReadonly[$name]))
        {
            $backtrace = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $backtrace = $backtrace[1] ?? null;
            if (!(
                (isset($backtrace['object']) && $backtrace['object'] instanceof \Imi\Bean\IBean)
                || (isset($backtrace['class']) && Text::startwith($backtrace['class'], 'Imi\\'))
            ))
            {
                throw new \RuntimeException('Cannot write to read-only application context');
            }
        }
        elseif ($readonly)
        {
            static::$contextReadonly[$name] = true;
        }
        static::$context[$name] = $value;
    }

    /**
     * 获取 imi 版本.
     *
     * @return string
     */
    public static function getImiVersion(): string
    {
        if (null !== static::$imiVersion)
        {
            return static::$imiVersion;
        }
        // composer
        $loaders = Composer::getClassLoaders();
        if ($loaders)
        {
            foreach ($loaders as $loader)
            {
                $ref = ReflectionContainer::getClassReflection(\get_class($loader));
                $fileName = \dirname($ref->getFileName(), 3) . '/composer.lock';
                if (is_file($fileName))
                {
                    $data = json_decode(file_get_contents($fileName), true);
                    foreach ($data['packages'] ?? [] as $item)
                    {
                        if ('yurunsoft/imi' === $item['name'])
                        {
                            return static::$imiVersion = $item['version'];
                        }
                    }
                }
            }
        }
        // git
        if (false !== strpos(`git --version` ?? '', 'git version') && preg_match('/\*([^\r\n]+)/', `git branch` ?? '', $matches) > 0)
        {
            return static::$imiVersion = trim($matches[1]);
        }

        return static::$imiVersion = 'Unknown';
    }

    /**
     * Get app 实例对象
     *
     * @return \Imi\Core\Contract\IApp
     */
    public static function getApp(): IApp
    {
        return static::$app;
    }
}
