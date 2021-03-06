<?php

use CodeIgniter\Session\Handlers\FileHandler;
use Config\App;
use Config\Autoload;
use CodeIgniter\Config\Services;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\HTTP\RequestResponse;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\Logger;
use Tests\Support\Autoloader\MockFileLocator;
use Tests\Support\HTTP\MockIncomingRequest;
use Tests\Support\Log\TestLogger;
use Tests\Support\Session\MockSession;

/**
 * @backupGlobals enabled
 */
class CommomFunctionsTest extends \CIUnitTestCase
{

	//--------------------------------------------------------------------

	public function setUp()
	{
		parent::setUp();

		unset($_ENV['foo'], $_SERVER['foo']);
	}

	//--------------------------------------------------------------------

	public function testStringifyAttributes()
	{
		$this->assertEquals(' class="foo" id="bar"', stringify_attributes(['class' => 'foo', 'id' => 'bar']));

		$atts = new stdClass;
		$atts->class = 'foo';
		$atts->id = 'bar';
		$this->assertEquals(' class="foo" id="bar"', stringify_attributes($atts));

		$atts = new stdClass;
		$this->assertEquals('', stringify_attributes($atts));

		$this->assertEquals(' class="foo" id="bar"', stringify_attributes('class="foo" id="bar"'));

		$this->assertEquals('', stringify_attributes([]));
	}

	// ------------------------------------------------------------------------

	public function testStringifyJsAttributes()
	{
		$this->assertEquals('width=800,height=600', stringify_attributes(['width' => '800', 'height' => '600'], TRUE));

		$atts = new stdClass;
		$atts->width = 800;
		$atts->height = 600;
		$this->assertEquals('width=800,height=600', stringify_attributes($atts, TRUE));
	}

	// ------------------------------------------------------------------------

	public function testEnvReturnsDefault()
	{
		$this->assertEquals('baz', env('foo', 'baz'));
	}

	public function testEnvGetsFromSERVER()
	{
		$_SERVER['foo'] = 'bar';

		$this->assertEquals('bar', env('foo', 'baz'));
	}

	public function testEnvGetsFromENV()
	{
		$_ENV['foo'] = 'bar';

		$this->assertEquals('bar', env('foo', 'baz'));
	}

	public function testEnvBooleans()
	{
		$_ENV['p1'] = 'true';
		$_ENV['p2'] = 'false';
		$_ENV['p3'] = 'empty';
		$_ENV['p4'] = 'null';

		$this->assertTrue(env('p1'));
		$this->assertFalse(env('p2'));
		$this->assertEmpty(env('p3'));
		$this->assertNull(env('p4'));
	}

	// ------------------------------------------------------------------------

	public function testRedirectReturnsRedirectResponse()
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$response = $this->createMock(\CodeIgniter\HTTP\Response::class);
		$routes = new \CodeIgniter\Router\RouteCollection(new \Tests\Support\Autoloader\MockFileLocator(new \Config\Autoload()));
		\CodeIgniter\Services::injectMock('response', $response);
		\CodeIgniter\Services::injectMock('routes', $routes);

		$routes->add('home/base', 'Controller::index', ['as' => 'base']);

		$response->method('redirect')
				->will($this->returnArgument(0));

		$this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, redirect('base'));
	}

	public function testRedirectDefault()
	{
		$this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, redirect());
	}

	// ------------------------------------------------------------------------

	public function testView()
	{
		$data = [
			'testString' => 'bar',
			'bar'		 => 'baz',
		];
		$expected = '<h1>bar</h1>';
		$this->assertContains($expected, view('\Tests\Support\View\Views\simple', $data, []));
	}

	public function testViewSavedData()
	{
		$data = [
			'testString' => 'bar',
			'bar'		 => 'baz',
		];
		$expected = '<h1>bar</h1>';
		$this->assertContains($expected, view('\Tests\Support\View\Views\simple', $data, ['saveData' => true]));
		$this->assertContains($expected, view('\Tests\Support\View\Views\simple'));
	}

	// ------------------------------------------------------------------------

	public function testViewCell()
	{
		$expected = 'Hello';
		$this->assertEquals($expected, view_cell('\CodeIgniter\View\SampleClass::hello'));
	}

	// ------------------------------------------------------------------------

	public function testEscapeBadContext()
	{
		$this->expectException(InvalidArgumentException::class);
		esc(['width' => '800', 'height' => '600'], 'bogus');
	}

	// ------------------------------------------------------------------------

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testSessionInstance()
	{
		$this->injectSessionMock();

		$this->assertInstanceOf(CodeIgniter\Session\Session::class, session());
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testSessionVariable()
	{
		$this->injectSessionMock();

		$_SESSION['notbogus'] = 'Hi there';

		$this->assertEquals('Hi there', session('notbogus'));
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testSessionVariableNotThere()
	{
		$this->injectSessionMock();

		$_SESSION['bogus'] = 'Hi there';
		$this->assertEquals(null, session('notbogus'));
	}

	// ------------------------------------------------------------------------

	public function testSingleService()
	{
		$timer1 = single_service('timer');
		$timer2 = single_service('timer');
		$this->assertFalse($timer1 === $timer2);
	}

	// ------------------------------------------------------------------------

	public function testRouteTo()
	{
		// prime the pump
		$routes = service('routes');
		$routes->add('path/(:any)/to/(:num)', 'myController::goto/$1/$2');

		$this->assertEquals('/path/string/to/13', route_to('myController::goto', 'string', 13));
	}

	// ------------------------------------------------------------------------

	public function testInvisible()
	{
		$this->assertEquals('Javascript', remove_invisible_characters("Java\0script"));
	}

	public function testInvisibleEncoded()
	{
		$this->assertEquals('Javascript', remove_invisible_characters("Java%0cscript", true));
	}

	// ------------------------------------------------------------------------

	public function testAppTimezone()
	{
		$this->assertEquals('America/Chicago', app_timezone());
	}

	// ------------------------------------------------------------------------

	public function testCSRFToken()
	{
		$this->assertEquals('csrf_test_name', csrf_token());
	}

	public function testHash()
	{
		$this->assertEquals(32, strlen(csrf_hash()));
	}

	public function testCSRFField()
	{
		$this->assertContains('<input type="hidden" ', csrf_field());
	}

	// ------------------------------------------------------------------------

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testOldInput()
	{
		$this->injectSessionMock();
		// setup from RedirectResponseTest...
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->config = new App();
		$this->config->baseURL = 'http://example.com';

		$this->routes = new RouteCollection(new MockFileLocator(new Autoload()));
		Services::injectMock('routes', $this->routes);

		$this->request = new MockIncomingRequest($this->config, new URI('http://example.com'), null, new UserAgent());
		Services::injectMock('request', $this->request);

		// setup & ask for a redirect...
		$_SESSION = [];
		$_GET = ['foo' => 'bar'];
		$_POST = ['bar' => 'baz', 'zibble' => serialize('fritz')];

		$response = new RedirectResponse(new App());
		$returned = $response->withInput();

		$this->assertEquals('bar', old('foo')); // regular parameter
		$this->assertEquals('doo', old('yabba dabba', 'doo')); // non-existing parameter
		$this->assertEquals('fritz', old('zibble')); // serialized parameter
	}

	// ------------------------------------------------------------------------

	public function testReallyWritable()
	{
		// cannot test fully on *nix
		$this->assertTrue(is_really_writable(WRITEPATH));
	}

	// ------------------------------------------------------------------------

	public function testSlashItem()
	{
		$this->assertEquals('/', slash_item('cookiePath')); // slash already there
		$this->assertEquals('', slash_item('cookieDomain')); // empty, so untouched
		$this->assertEquals('en/', slash_item('defaultLocale')); // slash appended
	}

	protected function injectSessionMock()
	{
		$defaults = [
			'sessionDriver' => 'CodeIgniter\Session\Handlers\FileHandler',
			'sessionCookieName' => 'ci_session',
			'sessionExpiration' => 7200,
			'sessionSavePath' => null,
			'sessionMatchIP' => false,
			'sessionTimeToUpdate' => 300,
			'sessionRegenerateDestroy' => false,
			'cookieDomain' => '',
			'cookiePrefix' => '',
			'cookiePath' => '/',
			'cookieSecure' => false,
		];

		$config = (object)$defaults;

		$session = new MockSession(new FileHandler($config), $config);
		$session->setLogger(new TestLogger(new Logger()));
		\CodeIgniter\Config\BaseService::injectMock('session', $session);
	}

}
