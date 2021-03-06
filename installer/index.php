<?php

use Illuminate\Http\Request;
use Illuminate\Config\FileLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Routing\Router;

/*
|--------------------------------------------------------------------------
| Load The Autoloader
|--------------------------------------------------------------------------
|
| The CMS relies heavily on Composer components. We'll need the autoloader
| to load those components, and then to load the CMS itself.
|
*/

$autoloader = require_once __DIR__.'/../cms/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The CMS
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of the CMS, and is
| the IoC container for the system binding all of the various parts.
|
*/

$cms = new Cms\Application;

/*
|--------------------------------------------------------------------------
| Define The Application Path
|--------------------------------------------------------------------------
|
| Here we just defined the path to the application directory. Most likely
| you will never need to change this value, as the default setup should
| work perfectly fine for the vast majority of all application setups.
|
*/

$cms->instance('path', __DIR__);
$cms->instance('path.base', dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Register The Autoloader
|--------------------------------------------------------------------------
|
| Inject the autoloader into the app.
|
*/

$cms->bind('autoloader', function() use($autoloader)
{
	return $autoloader;
});

/*
|--------------------------------------------------------------------------
| Detect The Application Environment
|--------------------------------------------------------------------------
|
| Illuminate takes a dead simple approach to application environments.
| Just specify the hosts that belong to a given environment, and we
| will quickly detect and set the application environment for you.
|
*/

$env = $cms->detectEnvironment(array(

	'local' => array('your-machine-name'),

));

/*
|--------------------------------------------------------------------------
| Load The Illuminate Facades
|--------------------------------------------------------------------------
|
| The facades provide a terser static interface over the various parts
| of the application, allowing their methods to be accessed through
| a mixtures of magic methods and facade derivatives. It's slick.
|
*/

Facade::clearResolvedInstances();

Facade::setFacadeApplication($cms);

/*
|--------------------------------------------------------------------------
| Register The Configuration Loader
|--------------------------------------------------------------------------
|
| The configuration loader is responsible for loading the configuration
| options for the application. By default we'll use the "file" loader
| but you are free to use any custom loaders with your application.
|
*/

$cms->bindIf('config.loader', function($cms)
{
	return new FileLoader(new Filesystem, $cms['path.base'].'/config');

}, true);

/*
|--------------------------------------------------------------------------
| Register Application Exception Handling
|--------------------------------------------------------------------------
|
| We will go ahead and register the application exception handling here
| which will provide a great output of exception details and a stack
| trace in the case of exceptions while an application is running.
|
*/

$cms->startExceptionHandling();

/*
|--------------------------------------------------------------------------
| Register The Configuration Repository
|--------------------------------------------------------------------------
|
| The configuration repository is used to lazily load in the options for
| this application from the configuration files. The files are easily
| separated by their concerns so they do not become really crowded.
|
*/

$config = new Config($cms['config.loader'], $env);

$cms->instance('config', $config);

/*
|--------------------------------------------------------------------------
| Set The Default Timezone
|--------------------------------------------------------------------------
|
| Here we will set the default timezone for PHP. PHP is notoriously mean
| if the timezone is not explicitly set. This will be used by each of
| the PHP date and date-time functions throoughout the application.
|
*/

date_default_timezone_set($config['app']['timezone']);

/*
|--------------------------------------------------------------------------
| Register The Alias Loader
|--------------------------------------------------------------------------
|
| The alias loader is responsible for lazy loading the class aliases setup
| for the application. We will only register it if the "config" service
| is bound in the application since it contains the alias definitions.
|
*/

$cms->registerAliasLoader($config['cms']['aliases']);

/*
|--------------------------------------------------------------------------
| Enable HTTP Method Override
|--------------------------------------------------------------------------
|
| Next we will tell the request class to allow HTTP method overriding
| since we use this to simulate PUT and DELETE requests from forms
| as they are not currently supported by plain HTML form setups.
|
*/

Request::enableHttpMethodParameterOverride();

/*
|--------------------------------------------------------------------------
| Register The View Path
|--------------------------------------------------------------------------
|
| The installer uses a special view path. Set that here.
|
*/

$cms['config']['view.paths'] = array($cms['path'].'/views/');

/*
|--------------------------------------------------------------------------
| Register The Core Service Providers
|--------------------------------------------------------------------------
|
| The Illuminate core service providers register all of the core pieces
| of the Illuminate framework including session, caching, encryption
| and more. It's simply a convenient wrapper for the registration.
|
*/

$providers = array(
	'Illuminate\Cookie\CookieServiceProvider',
	'Illuminate\Routing\ControllerServiceProvider',
	'Illuminate\Database\DatabaseServiceProvider',
	'Illuminate\Filesystem\FilesystemServiceProvider',
	'Illuminate\Encryption\EncryptionServiceProvider',
	'Illuminate\Session\SessionServiceProvider',
	'Illuminate\View\ViewServiceProvider',
);

foreach($providers as $provider)
{
	$cms->register(new $provider($cms));
}

/*
|--------------------------------------------------------------------------
| Boot the User's Session
|--------------------------------------------------------------------------
|
| By default, Laravel boots the session in the route's before filter.
| However, we want the plugins and modules to have access to the session
| data so we will start the session in advance. 
|
*/

Session::start($cms['cookie']);

/*
|--------------------------------------------------------------------------
| Load the Installation routes.
|--------------------------------------------------------------------------
|
| The installer stores all of its routes in the routes file. Let's boot
| that up now that everything else is already loaded.
|
*/

include $cms['path'].'/routes.php';

/*
|--------------------------------------------------------------------------
| Run the Installer.
|--------------------------------------------------------------------------
|
| Everything is set up. Let's run the installer!
|
*/

$cms['router']->dispatch(Request::createFromGlobals())->send();