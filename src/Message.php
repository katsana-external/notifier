<?php namespace Orchestra\Notifier;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Orchestra\Contracts\Notification\Message as MessageContract;

class Message extends Fluent implements MessageContract
{
    /**
     * Create a new Message instance.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  string|null  $subject
     *
     * @return static
     */
    public static function create($view, array $data = [], $subject = null)
    {
        return new static([
            'view'    => $view,
            'data'    => $data,
            'subject' => $subject,
        ]);
    }

    /**
     * Get data.
     *
     * @return array
     */
    public function getData()
    {
        return Arr::get($this->attributes, 'data', []);
    }

    /**
     * Get subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return Arr::get($this->attributes, 'subject', '');
    }

    /**
     * Get view.
     *
     * @return string|array
     */
    public function getView()
    {
        return Arr::get($this->attributes, 'view');
    }
}