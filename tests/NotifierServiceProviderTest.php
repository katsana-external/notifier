<?php namespace Orchestra\Notifier\TestCase;

use Mockery as m;
use Illuminate\Container\Container;
use Orchestra\Notifier\NotifierServiceProvider;

class NotifierServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Teardown the test environment.
     */
    public function tearDown()
    {
        m::close();
    }

    /**
     * Test Orchestra\Notifier\NotifierServiceProvider::register() method.
     *
     * @test
     */
    public function testRegisterMethod()
    {
        $app = m::mock('\Illuminate\Container\Container');

        $app->shouldReceive('singleton')->once()->with('orchestra.mail', m::type('Closure'))
                ->andReturnUsing(function ($n, $c) use ($app) {
                    return $c($app);
                })
            ->shouldReceive('singleton')->once()->with('orchestra.notifier', m::type('Closure'))
                ->andReturnUsing(function ($n, $c) use ($app) {
                    return $c($app);
                });

        $stub = new NotifierServiceProvider($app);
        $stub->register();
    }

    /**
     * Test Orchestra\Notifier\NotifierServiceProvider::boot() method.
     *
     * @test
     */
    public function testBootMethod()
    {
        $path = realpath(__DIR__.'/../src/');
        $app = new Container;

        $app['path.base'] = '/var/laravel';
        $app['config'] = $config = m::mock('\Illuminate\Config\Repository');
        $app['files'] = $files = m::mock('\Illuminate\Filesystem\Filesystem');

        $memoryProvider = m::mock('\Orchestra\Memory\Provider');

        $config->shouldReceive('package')->once()
                ->with('orchestra/notifier', "{$path}/config", 'orchestra/notifier')->andReturnNull();
        $files->shouldReceive('isDirectory')->once()->with("{$path}/config")->andReturn(true)
            ->shouldReceive('isDirectory')->once()->with("{$path}/lang")->andReturn(false)
            ->shouldReceive('isDirectory')->once()
                ->with("{$app['path.base']}/resources/views/packages/orchestra/notifier")->andReturn(false)
            ->shouldReceive('isDirectory')->once()->with("{$path}/views")->andReturn(false);

        $stub = new NotifierServiceProvider($app);

        $stub->boot();
    }

    /**
     * Test Orchestra\Notifier\NotifierServiceProvider::provides() method.
     *
     * @test
     */
    public function testProvidesMethod()
    {
        $app  = new Container;
        $stub = new NotifierServiceProvider($app);

        $this->assertEquals(array('orchestra.mail', 'orchestra.notifier'), $stub->provides());
    }

    /**
     * Test Orchestra\Notifier\NotifierServiceProvider is deferred.
     *
     * @test
     */
    public function testServiceIsDeferred()
    {
        $app  = new Container;
        $stub = new NotifierServiceProvider($app);

        $this->assertTrue($stub->isDeferred());
    }
}
