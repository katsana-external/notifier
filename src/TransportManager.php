<?php

namespace Orchestra\Notifier;

use Aws\Ses\SesClient;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Log\LogManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use Orchestra\Memory\Memorizable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;

class TransportManager extends Manager
{
    use Memorizable;

    /**
     * Use fallback.
     *
     * @var bool
     */
    protected $useFallback = false;

    /**
     * Create an instance of the SMTP Swift Transport driver.
     */
    protected function createSmtpDriver()
    {
        $config = $this->getTransportConfig();

        // Create the SMTP transport using Symfony's EsmtpTransport
        $transport = new EsmtpTransport(
            $config['host'],
            $config['port'],
            isset($config['encryption']) ? $config['encryption'] : null  // Set encryption in constructor
        );

        if (isset($config['username'])) {
            $transport->setUsername($config['username']);
            $transport->setPassword($this->getSecureConfig('password'));
        }

        if (isset($config['stream'])) {
            $transport->setStreamOptions($config['stream']);
        }

        return $transport;
    }

    /**
     * Create an instance of the Sendmail Swift Transport driver.
     */
    protected function createSendmailDriver()
    {
        $command = $this->getConfig('sendmail');
        
        // Use default command if not configured
        if (empty($command)) {
            $command = '/usr/sbin/sendmail -bs';
        }

        return new SendmailTransport($command);
    }

    /**
     * Create an instance of the Amazon SES Swift Transport driver.
     *
     * @return \Illuminate\Mail\Transport\SesTransport
     */
    protected function createSesDriver()
    {
        $config = [
            'version' => 'latest', 'service' => 'email',
            'key' => $this->getSecureConfig('key'),
            'secret' => $this->getSecureConfig('secret'),
            'region' => $this->getConfig('region'),
            'options' => [],
        ];

        return new SesTransport(
            new SesClient($this->addSesCredentials($config)),
            []
        );
    }

    /**
     * Add the SES credentials to the configuration array.
     *
     * @return array
     */
    protected function addSesCredentials(array $config)
    {
        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Create an instance of the Mail Swift Transport driver.
     *
     * @return \Symfony\Component\Mailer\Transport\SendmailTransport
     */
    protected function createMailDriver()
    {
        return new SendmailTransport();
    }

    /**
     * Create an instance of the Mailgun Swift Transport driver.
     *
     * @return \Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport
     */
    protected function createMailgunDriver()
    {
        return new \Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport(
            $this->getSecureConfig('secret'),
            $this->getConfig('domain')
        );
    }

    /**
     * Create an instance of the Log Swift Transport driver.
     *
     * @return \Illuminate\Mail\Transport\LogTransport
     */
    protected function createLogDriver()
    {
        $logger = $this->container->make(LoggerInterface::class);

        if ($logger instanceof LogManager) {
            $logger = $logger->channel($this->config['mail.log_channel']);
        }

        return new LogTransport($logger);
    }

    /**
     * Create an instance of the Array Swift Transport Driver.
     *
     * @return \Illuminate\Mail\Transport\ArrayTransport
     */
    protected function createArrayDriver()
    {
        return new ArrayTransport();
    }

    /**
     * Get a fresh Guzzle HTTP client instance.
     *
     * @return \GuzzleHttp\Client
     */
    protected function guzzle()
    {
        return new HttpClient(Arr::add(
            $this->getConfig('guzzle') ?? [], // This line expects the mock
            'connect_timeout',
            60
        ));
    }


    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!! $this->useFallback) {
            return $this->app['mail.manager']->mailer($driver)
                ->getSwiftMailer()
                ->getTransport();
        }

        return parent::driver($driver);
    }

    /**
     * Get the default mail driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        if ($this->attached() && $this->memory->has('email.driver')) {
            return $this->memory->get('email.driver');
        }

        $this->useFallback = true;

        return \config('mail.default');
    }

    /**
     * Get transport configuration.
     *
     * @return array
     */
    protected function getTransportConfig()
    {
        return $this->memory->get('email', []);
    }

    /**
     * Get transport configuration.
     *
     * @param  string  $key
     * @param  mixed  $default
     *
     * @return array
     */
    public function getConfig($key, $default = null)
    {
        return $this->memory->get("email.{$key}", $default);
    }

    /**
     * Get transport encrypted configuration.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     *
     * @return array
     */
    public function getSecureConfig($key = null, $default = null)
    {
        return $this->memory->secureGet("email.{$key}", $default);
    }
}
