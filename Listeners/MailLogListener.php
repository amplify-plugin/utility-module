<?php

namespace Amplify\System\Utility\Listeners;

use Amplify\System\Utility\Models\MailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class MailLogListener
{
    /**
     * Handle the event.
     *
     * @param  MessageSending|MessageSent  $event
     * @return void
     */
    public function handle($event)
    {
        if (! config('amplify.developer.log_email')) {
            return;
        }

        /**
         * @var \Symfony\Component\Mime\Email $message
         */
        $message = $event->message;

        /**
         * @var \Symfony\Component\Mime\Address[] $emails
         */
        $emails = $this->emailSerialize($message->getTo());
        $subject = $message->getSubject();
        $body = $message->getBody()->toString();

        MailLog::create([
            'status' => ($event instanceof MessageSent) ? 'sent' : 'sending',
            'email' => $emails,
            'subject' => $subject,
            'body' => $body,
            'data' => json_encode($event->data),
        ]);
    }

    /**
     * @param  \Symfony\Component\Mime\Address[]  $emails
     */
    private function emailSerialize(array $emails): array
    {
        return array_map(function ($email) {
            return $email->getAddress();
        }, $emails);

    }
}
