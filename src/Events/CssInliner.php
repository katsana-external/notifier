<?php

namespace Orchestra\Notifier\Events;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Part\AbstractPart;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CssInliner
{
    public function handle(MessageSending $sending): void
    {
        $message = $sending->message;
        $converter = new CssToInlineStyles();

        // Check if HTML body exists and convert it
        if ($message->getHtmlBody()) {
            $message->html($converter->convert($message->getHtmlBody()));
        }

        // Handle parts/attachments if needed
        if (method_exists($message, 'getBody')) {
            $body = $message->getBody();

            // Handle single TextPart
            if ($body instanceof AbstractPart && method_exists($body, 'getMediaType') && $body->getMediaType() === 'text/html') {
                $body->setBody($converter->convert($body->getBody()));
            }

            // Handle multipart messages
            if (method_exists($body, 'getParts')) {
                foreach ($body->getParts() as $part) {
                    if ($part instanceof AbstractPart && method_exists($part, 'getMediaType') && $part->getMediaType() === 'text/html') {
                        $part->setBody($converter->convert($part->getBody()));
                    }
                }
            }
        }
    }
}
