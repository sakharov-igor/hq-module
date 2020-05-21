<?php

namespace HQWHMCS;

use Symfony\Component\BrowserKit\Response;

abstract class Module
{
    /**
     * @var string
     */
    protected $routeParameter      = '';

    /**
     * @var string
     */
    protected $controllerAction    = '';

    /**
     * @var string
     */
    protected $migrationDir        =  'Migrations';

    /**
     * @var string
     */
    protected $hooksDir            = 'Hooks';

    /**
     * @var string
     */
    protected $defaultAction       = 'index';

    /**
     * @var string
     */
    protected $defaultRoute        = 'admin';

    /**
     * @var null
     */
    protected $request             = null;

    /**
     * @var array
     */
    protected $routes              = [];

    /**
     * @var array|mixed
     */
    protected $dependencyInjection = [];

//    protected $namespace;

    protected $moduleDir;

    /**
     * Module constructor.
     */
    public function __construct() {
    }

    /**
     * @return $this
     */
    public function dispatch() {
        return $this;
    }

    /**
     * @return object
     * @throws \ReflectionException
     */
    private function getControllerByCurrentRequest() {
        if (array_key_exists($this->routeParameter, $this->request)) {
            return $this->loadClass($this->routes[$this->request[$this->routeParameter]]['class']);
        } else {
            return $this->loadClass($this->routes[$this->defaultRoute]['class']);
        }
    }

    /**
     * @param $className
     *
     * @return object
     * @throws \ReflectionException
     */
    public function loadClass($className) {
        if (!$this->dependencyInjection[$className]) {
            return new $className();
        } else {
            $reflection = new \ReflectionClass($className);
            $func = [$this, 'loadClass'];

            return $reflection->newInstanceArgs(
                array_reduce($this->dependencyInjection[$className], function ($carry, $item) use ($func) {
                    $carry[] = is_string($item) ? call_user_func($func, $item) : $item;

                    return $carry;
                })
            );
        }
    }

    /**
     * @param $controller
     * @param bool $action
     *
     * @return mixed
     */
    private function callControllerAction($controller, $action = false) {
        $action = $action ? $action : $this->defaultAction;

        return call_user_func([$controller, $action]);
    }

    /**
     * @param $controller
     *
     * @return mixed
     */
    private function callControllerActionByCurrentRequest($controller) {
        if (array_key_exists($this->controllerAction, $this->request)) {
            if (method_exists($controller, $this->request[$this->controllerAction])) {
                return $this->callControllerAction($controller, $this->request[$this->controllerAction]);
            }

            return $this->callControllerAction($controller);
        }

        return $this->callControllerAction($controller);
    }

    /**
     * @param Response $response
     */
    public function sendResponse(Response $response) {
        $response->sendHeaders();
        $response->sendContent();
    }

    /**
     * Method for call and echo controller result
     *
     * @throws \ReflectionException
     */
    public function handleRequest() {
        $controller = $this->getControllerByCurrentRequest();

        $this->sendResponse(
            $this->callControllerActionByCurrentRequest($controller)
        );
    }

    /**
     * @param $version
     */
    public function runMigrations($version, $direction) {
        $files =  array_diff(scandir($this->_componentFullPath($this->migrationDir)), ['.', '..']);

        foreach ($files as $index => $file) {
            if (strpos($file, 'Migration') !== 0) return;

            $ver = str_replace('Migration', '', $file);
            $ver = (int) str_replace('.php', '', $ver);

            if ($ver <= (int) $version) {
                require_once $this->_componentFullPath($this->migrationDir) . '/' . $file;
                call_user_func((new \ReflectionObject($this))->getNamespaceName().'\Migrations\\' .
                    str_replace('.php', '', $file) . '::' . $direction);
            }
        }
    }

    /**
     * @param $vars
     */
    public function handleActivate($vars) {
        $this->runMigrations($vars['version'], 'up');
    }

    /**
     * @param $vars
     */
    public function handleDeactivate($vars) {
        $this->runMigrations($vars['version'], 'down');
    }

    /**
     * @param $vars
     */
    public function handleUpgrade($vars) {
        $this->runMigrations($vars['version'], 'up');
    }

    /**
     * @param $nameHook
     *
     * @return \Closure
     */
    public static function getHookFunction($nameHook) {
        $className = get_called_class();
        return function ($params = []) use ($nameHook, $className) {
            return (new $className)->handleHook($nameHook, $params);
        };
    }

    /**
     * @param string $hookName
     * @param array $params
     *
     * @return mixed
     */
    public static function dispatchHook($hookName, $params = []) {
        return call_user_func(self::getHookFunction($hookName), $params);
    }

    /**
     * @param $nameHook
     * @param $params
     *
     * @return array
     * @throws \ReflectionException
     */
    public function handleHook($nameHook, $params = []) {
        $files = array_diff(scandir($this->_componentFullPath($this->hooksDir)), ['.', '..']);

        foreach ($files as $file) {
            $nameHookFile = str_replace('.php', '', $file);

            if ($nameHook === $nameHookFile) {
                require_once $this->_componentFullPath($this->hooksDir) . '/' . $file;
                $hookObject = $this->loadClass((new \ReflectionObject($this))->getNamespaceName().'\Hooks\\' .
                    str_replace('.php', '', $file));
                $result = $hookObject->handle($params);
                if ($result && is_array($result)) {
                    $params = array_merge($result, $params);
                }
            }
        }

        return $params;
    }

    /**
     * generate absolute path of module component, like as Migrations, Hooks, etc
     *
     * @param $path
     * @return string
     */
    protected function _componentFullPath($path) {
        return sprintf("%s/%s", rtrim($this->moduleDir, "\/"), ltrim($path, "\/"));
    }
}
