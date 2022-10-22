# uri



#### 介绍
一个简单地解析和操作URL的类库


最低支持php7.2，通过composer安装：

```php
composer require filippo-toso/uri
```

基本的使用方式如下，通过`make`静态方法或手动实例化：

```php
use Ulthon\URI\URI;

$url = 'https://phpreturn.com/index/a6310ae8e7c561.html?name=john&emailjohn@smith.com#fragment';

$uri = URI::make($url);

// 或者

$uri = new URI($url);
```

实例化完成之后，就可以使用类库方法随心所欲的操作URL。

比如设置协议或域名：

```php
use Ulthon\URI\URI;

$url = 'phpreturn.com?name=john&emailjohn@smith.com';

$newUrl = URI::make($url)
    ->scheme('http') // 修改链接为: http://phpreturn.com/index/a6310ae8e7c561.html?name=john&emailjohn@smith.com
    ->domain('doc.ulthon.com') // 修改链接为: http://doc.ulthon.com/index/a6310ae8e7c561.html?name=john&emailjohn@smith.com
    ->url();  

```

支持的方法如下：

- scheme()
- user()
- pass()
- host()
- port()
- path()
- query()
- fragment()

如果传入参数，就是设置，否则只是读取。比如我们要读取域名：

```php
use Ulthon\URI\URI;

$url = 'http://phpreturn.com?name=john&emailjohn@smith.com';

$domain = URI::make($url)
    ->domain();
```

他还支持更复杂的用法，比如解析相对路径：

```php
use Ulthon\URI\URI;

$url = 'http://phpreturn.com/dir/sub/file.php?name=john&emailjohn@smith.com';

$relativeUrl = '../../hello.php';

$newUrl = URI::make($url)
    ->relative($relativeUrl) // 链接修改为: http://phpreturn.com/hello.php?name=john&emailjohn@smith.com
    ->url();
```

在设置和获取query时，支持通过点分隔符读取多级数组，比如想要获取这样的值`$_GET['post']['content']['html']` ,这时只需要使用`post.content.html`即可获取。

query的操作有这些：

- `add()` 添加get参数
- `remove()` 删除get参数
- `set()` 设置get参数
- `get()` 读取get参数

其中remove方法支持通过回调函数方式：

```php
$url = 'https://phpreturn.com/?utm_source=summer-mailer&utm_medium=email&utm_campaign=summer-sale';

$newUrl = URI::make($url)->remove(function ($key, $value) {
    return (bool)preg_match('#^utm_#si', $key);
})->url();
```

这样一个库，很简单，却很好用，提升开发幸福感。



## 基于

本项目基于 https://github.com/filippotoso/uri 项目开发，增加了更灵活的用法。

