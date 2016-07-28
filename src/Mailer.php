<?php

namespace Orchestra\Notifier;

use Closure;
use Swift_Mailer;
use Illuminate\Support\Str;
use Orchestra\Memory\Memorizable;
use Illuminate\Contracts\Queue\Job;
use SuperClosure\SerializableClosure;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Contracts\Queue\Factory as QueueContract;
use Illuminate\Contracts\Mail\Mailable as MailableContract;

class Mailer
{
    use Memorizable;

    /**
     * Application instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * Mailer instance.
     *
     * @var \Illuminate\Contracts\Mail\Mailer
     */
    protected $mailer;

    /**
     * The queue implementation.
     *
     * @var \Illuminate\Contracts\Queue\Factory
     */
    protected $queue;

    /**
     * Transporter instance.
     *
     * @var \Orchestra\Notifier\TransportManager
     */
    protected $transport;

    /**
     * Construct a new Mail instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $app
     * @param  \Orchestra\Notifier\TransportManager  $transport
     */
    public function __construct($app, TransportManager $transport)
    {
        $this->app       = $app;
        $this->transport = $transport;
    }

    /**
     * Set the global from address and name.
     *
     * @param  string  $address
     * @param  string|null  $name
     *
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->getMailer()->alwaysFrom($address, $name);
    }

    /**
     * Set the global to address and name.
     *
     * @param  string  $address
     * @param  string|null  $name
     *
     * @return void
     */
    public function alwaysTo($address, $name = null)
    {
        $this->getMailer()->alwaysTo($address, $name);
    }

    /**
     * Register the Swift Mailer instance.
     *
     * @return \Illuminate\Contracts\Mail\Mailer
     */
    public function getMailer()
    {
        if (! $this->mailer instanceof MailerContract) {
            $this->transport->setMemoryProvider($this->memory);

            $this->mailer = $this->resolveMailer();
        }

        return $this->mailer;
    }

    /**
     * Allow Orchestra Platform to either use send or queue based on
     * settings.
     *
     * @param  \Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @param  string|null  $queue
     *
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function push($view, array $data = [], $callback = null, $queue = null)
    {
        $method = 'send';
        $memory = $this->memory;

        if ($this->shouldBeQueued()) {
            $method = 'queue';
        }

        return $this->{$method}($view, $data, $callback, $queue);
    }

    /**
     * Force Orchestra Platform to send email directly.
     *
     * @param  \Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     *
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function send($view, array $data = [], $callback = null)
    {
        $mailer = $this->getMailer();

        if ($view instanceof MailableContract) {
            return $view->send($this->getMailer());
        }

        $mailer->send($view, $data, $callback);

        return new Receipt($mailer, false);
    }

    /**
     * Force Orchestra Platform to send email using queue.
     *
     * @param  \Illuminate\Contracts\Mail\Mailable|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @param  string|null  $queue
     *
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function queue($view, array $data = [], $callback = null, $queue = null)
    {
        if ($view instanceof MailableContract) {
            return $view->queue($this->queue);
        }

        $callback = $this->buildQueueCallable($callback);

        $with = [
            'view'     => $view,
            'data'     => $data,
            'callback' => $callback,
        ];

        $this->queue->push('orchestra.mail@handleQueuedMessage', $with, $queue);

        return new Receipt($this->getMailer(), true);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * @param  string  $queue
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     *
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function onQueue($queue, $view, array $data = [], $callback = null)
    {
        return $this->queue($view, $data, $callback, $queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * This method didn't match rest of framework's "onQueue" phrasing. Added "onQueue".
     *
     * @param  string  $queue
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return mixed
     */
    public function queueOn($queue, $view, array $data , $callback = null)
    {
        return $this->onQueue($queue, $view, $data, $callback);
    }

    /**
     * Build the callable for a queued e-mail job.
     *
     * @param  mixed  $callback
     *
     * @return mixed
     */
    protected function buildQueueCallable($callback)
    {
        if (! $callback instanceof Closure) {
            return $callback;
        }

        return serialize(new SerializableClosure($callback));
    }

    /**
     * Handle a queued e-mail message job.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @param  array  $data
     *
     * @return void
     */
    public function handleQueuedMessage(Job $job, $data)
    {
        $this->send($data['view'], $data['data'], $this->getQueuedCallable($data));

        $job->delete();
    }

    /**
     * Get the true callable for a queued e-mail message.
     *
     * @param  array  $data
     *
     * @return mixed
     */
    protected function getQueuedCallable(array $data)
    {
        if (Str::contains($data['callback'], 'SerializableClosure')) {
            return with(unserialize($data['callback']))->getClosure();
        }

        return $data['callback'];
    }

    /**
     * Setup mailer.
     *
     * @return \Illuminate\Contracts\Mail\Mailer
     */
    protected function resolveMailer()
    {
        $from   = $this->memory->get('email.from');
        $mailer = $this->app->make('mailer');

        // If a "from" address is set, we will set it on the mailer so that
        // all mail messages sent by the applications will utilize the same
        // "from" address on each one, which makes the developer's life a
        // lot more convenient.
        if (is_array($from) && ! empty($from['address'])) {
            $mailer->alwaysFrom($from['address'], $from['name']);
        }

        if ($this->queue instanceof QueueContract) {
            $mailer->setQueue($this->queue);
        }

        $mailer->setSwiftMailer(new Swift_Mailer($this->transport->driver()));

        return $mailer;
    }

    /**
     * Should the email be send via queue.
     *
     * @return bool
     */
    public function shouldBeQueued()
    {
        return $this->memory->get('email.queue', false);
    }

    /**
     * Set the queue manager instance.
     *
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     *
     * @return $this
     */
    public function setQueue(QueueContract $queue)
    {
        $this->queue = $queue;

        return $this;
    }
}
