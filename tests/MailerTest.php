<?php namespace Orchestra\Notifier\TestCase;

use Mockery as m;
use Illuminate\Container\Container;
use Illuminate\Support\SerializableClosure;
use Orchestra\Notifier\Mailer;

class MailerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Application instance.
     *
     * @var Illuminate\Foundation\Application
     */
    private $app = null;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        $this->app = new Container;

        $memory = m::mock('\Orchestra\Memory\Provider')->makePartial();

        $memory->shouldReceive('get')->with('email')->andReturn(array('driver' => 'mail'))
            ->shouldReceive('get')->with('email.from')->andReturn(array(
                'address' => 'hello@orchestraplatform.com',
                'name'    => 'Orchestra Platform',
            ));

        $this->app['orchestra.memory'] = $memory;
        $this->app['mailer'] = m::mock('\Illuminate\Mail\Mailer');
    }

    /**
     * Teardown the test environment.
     */
    public function tearDown()
    {
        unset($this->app);
        m::close();
    }

    /**
     * Test Orchestra\Notifier\Mailer::push() method uses Mail::send().
     *
     * @test
     */
    public function testPushMethodUsesSend()
    {
        $app    = $this->app;
        $memory = $app['orchestra.memory'];
        $mailer = $app['mailer'];

        $memory->shouldReceive('get')->twice()->with('email.queue', false)->andReturn(false);
        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->twice()->with('foo.bar', array('foo' => 'foobar'), '')->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->push('foo.bar', array('foo' => 'foobar'), ''));
        $this->assertTrue($stub->push('foo.bar', array('foo' => 'foobar'), ''));
    }

    /**
     * Test Orchestra\Notifier\Mailer::push() method uses Mail::queue().
     *
     * @test
     */
    public function testPushMethodUsesQueue()
    {
        $app = $this->app;
        $memory = $app['orchestra.memory'];
        $app['queue'] = $queue = m::mock('QueueListener');

        $with = array(
            'view'     => 'foo.bar',
            'data'     => array('foo' => 'foobar'),
            'callback' => function () {

            },
        );

        $memory->shouldReceive('get')->once()->with('email.queue', false)->andReturn(true);
        $queue->shouldReceive('push')->once()
            ->with('orchestra.mail@handleQueuedMessage', m::type('Array'), m::any())->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->push($with['view'], $with['data'], $with['callback']));
    }

    /**
     * Test Orchestra\Notifier\Mailer::send() method.
     *
     * @test
     */
    public function testSendMethod()
    {
        $app = $this->app;
        $mailer = $app['mailer'];

        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->once()->with('foo.bar', array('foo' => 'foobar'), '')->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->send('foo.bar', array('foo' => 'foobar'), ''));
    }

    /**
     * Test Orchestra\Notifier\Mailer::send() method using mail.
     *
     * @test
     */
    public function testSendMethodViaMail()
    {
        $app = array(
            'orchestra.memory' => $memory = m::mock('\Orchestra\Memory\Provider')->makePartial(),
            'mailer' => $mailer = m::mock('\Illuminate\Mail\Mailer'),
        );

        $memory->shouldReceive('get')->with('email')->andReturn(array('driver' => 'mail'))
            ->shouldReceive('get')->with('email.from')->andReturn(array(
                'address' => 'hello@orchestraplatform.com',
                'name'    => 'Orchestra Platform',
            ));

        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->once()->with('foo.bar', array('foo' => 'foobar'), '')->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->send('foo.bar', array('foo' => 'foobar'), ''));
    }

    /**
     * Test Orchestra\Notifier\Mailer::send() method using sendmail.
     *
     * @test
     */
    public function testSendMethodViaSendMail()
    {
        $app = array(
            'orchestra.memory' => $memory = m::mock('\Orchestra\Memory\Provider')->makePartial(),
            'mailer' => $mailer = m::mock('\Illuminate\Mail\Mailer'),
        );

        $memory->shouldReceive('get')->with('email')->andReturn(array(
                'driver'   => 'sendmail',
                'sendmail' => '/bin/sendmail -t',
            ))
            ->shouldReceive('get')->with('email.from')->andReturn(array(
                'address' => 'hello@orchestraplatform.com',
                'name'    => 'Orchestra Platform',
            ));

        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->once()->with('foo.bar', array('foo' => 'foobar'), '')->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->send('foo.bar', array('foo' => 'foobar'), ''));
    }

    /**
     * Test Orchestra\Notifier\Mailer::send() method using smtp.
     *
     * @test
     */
    public function testSendMethodViaSmtp()
    {
        $app = array(
            'orchestra.memory' => $memory = m::mock('\Orchestra\Memory\Provider')->makePartial(),
            'mailer' => $mailer = m::mock('\Illuminate\Mail\Mailer'),
        );

        $memory->shouldReceive('get')->with('email')->andReturn(array(
                'driver'     => 'smtp',
                'host'       => 'smtp.mailgun.org',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'hello@orchestraplatform.com',
                'password'   => 123456,
            ))
            ->shouldReceive('get')->with('email.from')->andReturn(array(
                'address' => 'hello@orchestraplatform.com',
                'name'    => 'Orchestra Platform',
            ));

        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->once()->with('foo.bar', array('foo' => 'foobar'), '')->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->send('foo.bar', array('foo' => 'foobar'), ''));
    }

    /**
     * Test Orchestra\Notifier\Mailer::send() method using invalid driver
     * throws exception.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testSendMethodViaInvalidDriverThrowsException()
    {
        $app = array(
            'orchestra.memory' => $memory = m::mock('\Orchestra\Memory\Provider')->makePartial(),
            'mailer' => $mailer = m::mock('\Illuminate\Mail\Mailer'),
        );

        $memory->shouldReceive('get')->with('email')->andReturn(array('driver' => 'foobar'));

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $stub->send('foo.bar', array('foo' => 'foobar'), '');
    }

    /**
     * Test Orchestra\Notifier\Mailer::queue() method.
     *
     * @test
     */
    public function testQueueMethod()
    {
        $app = $this->app;
        $app['queue'] = $queue = m::mock('QueueListener');

        $with = array(
            'view'     => 'foo.bar',
            'data'     => array('foo' => 'foobar'),
            'callback' => function () {

            },
        );

        $queue->shouldReceive('push')->once()
            ->with('orchestra.mail@handleQueuedMessage', m::type('Array'), m::any())->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->queue($with['view'], $with['data'], $with['callback']));
    }

    /**
     * Test Orchestra\Notifier\Mailer::queue() method when a class name
     * is given.
     *
     * @test
     */
    public function testQueueMethodWhenClassNameIsGiven()
    {
        $app = $this->app;
        $app['queue'] = $queue = m::mock('QueueListener');

        $with = array(
            'view'     => 'foo.bar',
            'data'     => array('foo' => 'foobar'),
            'callback' => 'FooMailHandler@foo',
        );

        $queue->shouldReceive('push')->once()
            ->with('orchestra.mail@handleQueuedMessage', $with, '')
            ->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $this->assertTrue($stub->queue($with['view'], $with['data'], $with['callback']));
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function queueMessageDataProvdier()
    {
        $closure = function () {

        };
        $callback = new SerializableClosure($closure);

        return array(
            array(
                'view'     => 'foo.bar',
                'data'     => array('foo' => 'foobar'),
                'callback' => serialize($callback),
            ),
            array(
                'view'     => 'foo.bar',
                'data'     => array('foo' => 'foobar'),
                'callback' => "hello world",
            )
        );
    }

    /**
     * Test Orchestra\Notifier\Mailer::handleQueuedMessage() method.
     *
     * @test
     * @dataProvider queueMessageDataProvdier
     */
    public function testHandleQueuedMessageMethod($view, $data, $callback)
    {
        $app = $this->app;
        $mailer = $app['mailer'];
        $job = m::mock('Job');

        $job->shouldReceive('delete')->once()->andReturn(null);
        $mailer->shouldReceive('setSwiftMailer')->once()->andReturn(null)
            ->shouldReceive('alwaysFrom')->once()->with('hello@orchestraplatform.com', 'Orchestra Platform')
            ->shouldReceive('send')->once()
                ->with($view, $data, m::any())->andReturn(true);

        $stub = with(new Mailer($app))->attach($app['orchestra.memory']);
        $stub->handleQueuedMessage($job, compact('view', 'data', 'callback'));
    }
}
