# Router

## 关于
完全自己开发的路由类库，不依赖任何第三方类库，开箱即用，仿部分Laravel路由功能，后期将加入路由缓存功能。

## 安装
1. composer安装
```shell
composer require caichuanhai/router
```

2. 普通安装
下载：`[https://github.com/caichuanhai/Router](https://github.com/caichuanhai/Router)`
将`src/Router.php`放入自己项目任意目录中。

## 使用

- #### 引入类库
```php
require_once path/to/vendor/autoload.php
//或
require_once path/to/Router.php
```
调用时可使用两种方式：
```php
use caichuanhai\router;
Router::get('a', controller@method);
//或
caichuanhai\Router::get('a', controller@method);
```

- #### 路由入门
最基本的路由只接收一个 URI 和一个闭包，并以此为基础提供一个非常简单优雅的路由定义方法：
```php
Router::get('hello', function () {
    return 'Hello, Welcome to my world';
});
```

- #### 有效路由定义
我们可以注册路由来响应任何 HTTP 请求动作：
```php
Route::get($uri, controller@method);
Route::post($uri, $callback);
Route::put($uri, $callback);
Route::patch($uri, $callback);
Route::delete($uri, $callback);
Route::options($uri, $callback);
Route::any($uri, $callback); /*匹配任意HTTP请求动作*/
```

- #### 回调函数路由
有时我们访问的链接不需要定向到具体的控制器，只需运行一个函数即可
```php
Router::get('c/caichuanhai', function (){
	...code
});
```

- #### 路由规则定义
设定路由规则的时候可以使用正则，也可以使用已经预定好的类型
```php
Router::post('a/b/(:num)', 'a@bpost')
```

表示匹配 `a/b/`后面接数字的URL，等同于：
```php
Router::post('a/b/[0-9]+', 'a@bpost')
```

如果访问`a/b/1/2/3`，则表示运行a控制器bpost方法，并用传参2和3，等同于:
```php
$cch = new a();
$cch->$bpost(2, 3)
```

如果访问`a/b/c`则不会匹配到该路由。


- #### 命名路由
命名路由为重定向提供了方便，实现起来也很简单，在路由定义之后使用 `name` 方法链的方式来定义该路由的名称：
```php
Route::get('user/profile', 'UserController@showProfile')->name('profile');
```
表示将该路由命名为`profile`，在其他地方如果我们想直接加载该路由方法时，可使用：
```php
Router::redirect('profile', [$param]);
```
直接运行`UserController@showProfile`，后面的`$param`可选，表示传递到该方法的参数数组。

- #### 路由分组
路由分组的目的是让我们在多个路由中共享相同的路由属性，比如名称前缀和路由前缀等，这样的话我们定义了大量的路由时就不必为每一个路由单独定义属性。
```php
Router::prefix('cai')->name('cch.')->group(function (){
	Router::any('e/f', 'e@f')->name('123');
	Router::any('g/[0-9]', 'e@f')->name('hai');

});
```

##### 路由前缀
`prefix` 方法可以用来为分组中每个路由添加一个给定 URI 前缀，例如，你可以为分组中所有路由 URI 添加 `admin` 前缀 ：
```php
Route::prefix('admin')->group(function () {
    Route::get('users', function () {
        // Matches The "/admin/users" URL
    });
});
```
这样我们就可以通过 `http://blog.test/admin/users` 访问路由了。

##### 路由名称前缀
`name` 方法可通过传入字符串为分组中的每个路由名称设置前缀，例如，你可能想要在所有分组路由的名称前添加 `admin` 前缀，由于给定字符串和指定路由名称前缀字符串完全一样，所以需要在前缀字符串末尾后加上 `.` 字符：
```php
Route::name('admin.')->group(function () {
    Route::get('users', function () {
        // 新的路由名称为 "admin.users"...
    })->name('users');
});
```

- #### URL路由匹配
当当前访问的URL在设定的路由表规则没有匹配到时，我们会根据URL的路径在控制器文件夹下自行查找对应控制器和方法，比如访问：`http://www.domain.com/a/b/c`这个URL，在路由表中若没有匹配的规则，则会继续在设定的控制器文件夹下查找是否有`a`文件夹或者`a.php`这个控制器，若有`a`文件夹则会查找该文件夹下有没有`b.php`控制器。
在路由表规则匹配不到的情况下，则会使用这种最原始的查找方法来加载对应控制器和方法。

- #### 默认路由
当访问根目录时，会直接访问默认路由，设置方法如下：
```php
Router::setDefaultRoute('cai@chuanhai');
```

- #### 404路由
当路由表匹配和URL原始匹配都无法匹配到路由时，则会调用404路由，要设定404路由对应的控制器和方法，使用如下代码：
```php
Router::set404Route('cai@chuanhai404');
```

若在其他地方需要直接访问404路由，可使用：
```php
Router::redirect404();
```

- #### 最终，运行路由功能
以上路由规则设置好后，调用`run`来运行整个路由功能
```php
Router::run($conpath);
```
其中`$conpath`为项目控制器所在路径，必须要填，不然Router不知道控制器放哪，也就无法检测控制器是否存在。

> 注：代码中已经实现了中间件的设置，但中间件功能的执行还未完成，所以些功能暂时不可用，将在后续版本中完善。