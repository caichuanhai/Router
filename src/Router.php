<?php
namespace caichuanhai;

class Router
{
	const REQUEST = array('get', 'post', 'options', 'put', "delete", 'patch', 'any');

	const REGEXPATTERN = array('(:any)' => '[^/]+', '(:num)' => '[0-9]+', '(:all)' => '.*');

	static $routeMap = array(); /*路由表*/

	static $groupRule = array('name' => null, 'prefix' => null, 'middleware' => null); /*保存要应用于group中的规则*/

	private static $_defaultRoute = null; /*默认路由*/

	private static $_404Route = null; /*404路由*/

	private static $_defaultMethod = 'index'; /*默认方法*/

	private static $_curParam = array(); /*当前链接传递给控制器的参数*/

	private static $_curRoute = null; /*当前链接所对应的路由，path\controller@method */

	private static $_conpath = './'; /*控制器所在文件夹，用于检测控制器是否存在，以及加载控制器*/

	private $_pattern = null; /*当前规则中的匹配模式，即要设置的路由*/

	private $_finalPattern = null; /*最终保存的路由匹配模式，即prefix加pattern*/

	private $_name = null; /*当前规则路由的名字*/

	private $_requestMethod = 'any'; /*当前规则路由表的默认请求类型*/

	private $_middleware = array(); /*当前路由表应用的中间件*/

	private $_prefix = null; /*路由前缀，只针对后面的group，若无group，则无效*/

    private function __construct(){}

    private function __clone(){}

    /**
     * 从调用静态方法开始，实例化对象并返回
     */
    public static function __callStatic($method, $arg)
    {
        $_this = new self();
        call_user_func_array(array(&$_this, $method), $arg);
        return $_this;
    }

    /**
     * 通过一开始调用静态方法返回的对象来调用其他方法
     * 先对要调用的方法进行处理才能准确知道要调用的真实方法
     */
    public function __call($method, $arg)
    {
    	$method = strtolower($method);
        if(in_array($method, self::REQUEST))
        {
        	array_unshift($arg, $method);
        	$method = '_setRoute';
        }
        else
        {
        	$method = '_set'.ucfirst($method);
        }
        call_user_func_array(array(&$this, $method), $arg);
        return $this;
    }

    /**
     * 最终设置并保存规则的方法，无论什么样的规则，一定会调用到此函数，否则该规则就不成立
     * @param string $requestMethod 请求的HTTP类型，一定存在于REQUEST中
     * @param string||regex $pattern       匹配模式，可以是字符串也可以是正则
     * @param string $target        该路由的目标方法 controller@method
     */
    private function _setRoute($requestMethod = 'any', $pattern, $target)
    {
    	$this->_pattern = $pattern;
    	$this->_requestMethod = $requestMethod;

    	$this->_setRouteFinalPattern();

    	$routerArr = array(
    		'target' => $target,
    		'name' => $this->_name,
    		'middleware' => !empty($this->_middleware) ? $this->_middleware : self::$groupRule['middleware']
    	);

    	if(!isset(self::$routeMap[$this->_finalPattern])) self::$routeMap[$this->_finalPattern][$requestMethod] = $routerArr;
		else self::$routeMap[$this->_finalPattern][$requestMethod] = $routerArr;
    }

    /**
     * 设置路由的名称
     * @param string $name
     */
	private function _setName($name)
	{
		$this->_name = isset(self::$groupRule['name']) ? self::$groupRule['name'].$name : $name;
		if(isset($this->_finalPattern)) self::$routeMap[$this->_finalPattern][$this->_requestMethod]['name'] = $this->_name;
	}

	/**
	 * 设置中间件
	 * @param string||Array $middleware 单个中间件或者中间件数组
	 */
	private function _setMiddleware($middleware)
	{
		$this->_middleware = is_array($middleware) ? $middleware : array($middleware);
		if(isset($this->_finalPattern)) self::$routeMap[$this->_finalPattern][$this->_requestMethod]['middleware'] = $this->_middleware;
	}

	/**
	 * 设置完整路由匹配模式，由pattern和group中的prefix组成
	 * @return String 路由模式
	 */
	private function _setRouteFinalPattern()
	{
		if(isset($this->_pattern))
		{	
			$routePattern = '';

			if(isset(self::$groupRule['prefix'])) $routePattern .= self::$groupRule['prefix'].'/';

			$routePattern .= $this->_pattern;

			$this->_finalPattern = $routePattern;
		}

	}

	/**
	 * 设置路由前缀，只针对后面的group设置
	 * @param string $prefix 路由前缀
	 */
	private function _setPrefix($prefix)
	{
		$this->_prefix = $prefix;
	}

	/**
	 * 设置路由组，回调里就是该组的路由规则设置
	 * @param function $callback 设置路由的回调函数
	 */
	private function _setGroup($callback = null)
	{
		if(is_callable($callback))
		{
			self::$groupRule = array('name' => $this->_name, 'prefix' => $this->_prefix, 'middleware' => $this->_middleware);

			call_user_func($callback);

			self::$groupRule = array('name' => null, 'prefix' => null, 'middleware' => null);
		}
	}

	static function setDefaultRoute($defaultRoute)
	{
		self::$_defaultRoute = $defaultRoute;
	}

	static function set404Route($route)
	{
		self::$_404Route = $route;
	}

	/**
	 * 运行路由功能
	 * @param  string $conpath 控制器所在路径
	 * @return [type]          [description]
	 */
	static function run($conpath = '')
	{
		if(!empty($conpath)) self::$_conpath = rtrim($conpath, '/').'/';

		$url = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '/';

		if($url == '/')
		{
			/*域名根目录，直接默认路由*/
			self::$_curRoute = self::$_defaultRoute;
			return;
		}
		
		self::_dealPatternReg();
		$found = self::_foundRouteViaMap();

		if(!$found)
		{
			$found = self::_foundRouteViaUrl();
		}
		
		if(!$found) self::$_curRoute = self::$_404Route;

		self::loadController();
	}

	/**
	 * 直接根据URL找对应路径的控制器和方法，不区分HTTP请求类型
	 * @return boolean 是否有找到对应的控制器和方法
	 */
	private static function _foundRouteViaUrl()
	{
		$found = false;

		$url = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '/';
		$arrayUrl = explode('/', $url);
		$classPath = '';
		while(isset($arrayUrl[0]) AND is_dir(self::$_conpath.$classPath.$arrayUrl[0]))
		{
			$classPath .= array_shift($arrayUrl).'\\';
		}

		$className = array_shift($arrayUrl);

		if(isset($arrayUrl[0])) $methodName = array_shift($arrayUrl);
		else $methodName = self::$_defaultMethod;

		/*看看对应的控制器类文件是否存在*/
		$controllerFile = self::$_conpath.$classPath.$className.'.php';
		if(file_exists($controllerFile))
		{
			require_once $controllerFile;
			/*如果使用反射，则控制器中的__call和__callStatic可能无法正常使用，可根据需要取消注释*/
			// $refClass = new \ReflectionClass($className);
			// if($refClass->hasMethod($methodName) AND $refClass->getMethod($methodName)->isPublic())
			// {
				self::$_curRoute = $classPath.$className.'@'.$methodName;
				self::$_curParam = $arrayUrl;
				$found = true;
			// }
		}

		return $found;
	}

	/**
	 * 通过保存的路由表来查找当前链接对应的跌幅
	 * @return boolean 是否有找到对应的控制器和方法
	 */
	private static function _foundRouteViaMap()
	{
		$found = false;
		$curRequestMethod = strtolower($_SERVER['REQUEST_METHOD']);
		$url = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '/';

		foreach(self::$routeMap as $pattern => $route)
		{
			if(!isset($route[$curRequestMethod]) AND !isset($route['any']))
			{
				/*没有对应的http请求类型*/
				continue;
			}

			$paramArray = array();
			if( (isset($route[$curRequestMethod]) OR isset($route['any'])) AND preg_match($pattern, $url, $matches))
			{
				foreach($route as $k => $v)
				{
					if($k == $curRequestMethod OR $k == 'any')
					{
						self::$_curRoute = $v['target'];
						break;
					}
				}
				self::$_curParam = explode('/', trim(str_replace($matches[0], '', $url), '/'));
				$found = true;
				break;
			}
		}

		return $found;
	}

	/**
	 * 将routeMap中的路由规则中的正则设置好
	 */
	private static function _dealPatternReg()
	{
		$search = array();
		$replace = array();
		foreach(self::REGEXPATTERN as $k => $v)
		{
			$search[] = $k;
			$replace[] = $v;
		}

		$routeMapPattern = array_keys(self::$routeMap);
		foreach($routeMapPattern as $k => &$v)
		{
			$v = '/^'.str_replace($search, $replace, str_replace('/', '\/', $v)).'/';
		}
		self::$routeMap = array_combine($routeMapPattern, array_slice(self::$routeMap, 0));
	}

	/**
	 * 加载控制器并运行方法
	 * @param  string $route 控制器方法路径，格式为：path\controller@method，不传则取self::$_curRoute
	 * @param  array  $param self::$_curParam
	 * @param  bool  $is404 当前调用控制器是否为404控制器
	 * @return
	 */
	static function loadController($route = '', $param = array(), $is404 = false)
	{
		if(empty($route)) $route = self::$_curRoute;
		if(empty($param)) $param = self::$_curParam;

		if(is_callable($route))
		{
			/*如果该路由对应的route是一个回调函数，则直接调用，不用再解析了*/
			call_user_func_array($route, $param);
			return;
		}
		else
		{
			$route = explode('@', $route);
			$method = array_pop($route);
			$class = explode('\\', $route[0]);
			$className = array_pop($class);

			$controllerFile = self::$_conpath.'/'.$route[0].'.php';
			if(file_exists($controllerFile))
			{
				require_once $controllerFile;
				/*如果使用反射，则控制器中的__call和__callStatic可能无法正常使用，可根据需要取消注释*/
				// $refClass = new \ReflectionClass($className);
				// if($refClass->hasMethod($method) AND $refClass->getMethod($method)->isPublic())
				// {
					$CCH = new $className();
					call_user_func_array(array(&$CCH, $method), $param);
					return;
				// }
			}

			if(!$is404) self::redirect404();
			else exit('404 page not found');
		}
	}

	/**
	 * 显示设定的404页面
	 */
	static function redirect404()
	{
		self::loadController(self::$_404Route, array(), true);
	}

	/**
	 * 跳转到指定路由名的控制器方法
	 * @param  string $name 路由名
	 * @param  array $param 要传递到该路由的参数，索引数组
	 */
	static function redirect($name, $param = array())
	{
		foreach(self::$routeMap as $v)
		{
			foreach($v as $sv)
			{
				if($sv['name'] == $name)
				{
					self::loadController($sv['target'], $param);
					return;
				}
			}
		}
	}

}