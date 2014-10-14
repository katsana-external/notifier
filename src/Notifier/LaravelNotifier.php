<?php namespace Orchestra\Notifier;

use Closure;
use Illuminate\Contracts\Mail\Mailer as Mail;
use Orchestra\Contracts\Notification\Message;
use Orchestra\Contracts\Notification\Recipient;
use Orchestra\Contracts\Notification\Notification;

class LaravelNotifier implements Notification
{
    /**
     * Mailer instance.
     *
     * @var \Illuminate\Contracts\Mail\Mailer
     */
    protected $mailer;

    /**
     * Setup Illuminate Mailer.
     *
     * @param  \Illuminate\Contracts\Mail\Mailer  $mailer
     */
    public function __construct(Mail $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send notification via API.
     *
     * @param  \Orchestra\Contracts\Notification\Recipient  $user
     * @param  \Orchestra\Contracts\Notification\Message  $message
     * @param  \Closure  $callback
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function send(Recipient $user, Message $message, Closure $callback = null)
    {
        $view    = $message->view;
        $data    = $message->data;
        $subject = $message->subject;

        // Send the email directly using Illuminate\Contracts\Mail\Mailer interface.
        $this->mailer->send($view, $data, function ($mail) use ($user, $subject, $callback) {
            // Set the recipient detail.
            $mail->to($user->getRecipientEmail(), $user->getRecipientName());

            // Only append the subject if it was provided.
            ! empty($subject) && $mail->subject($subject);

            // Run any callback if provided.
            is_callable($callback) && call_user_func_array($callback, func_get_args());
        });

        return new Receipt($this->mailer, false);
    }
}
