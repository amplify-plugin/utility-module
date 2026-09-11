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

        $uniqueId = $message
            ->getHeaders()
            ->get('X-Amplify-Mail-Id')
            ?->getBodyAsString();

        if ($event->message instanceof MessageSending) {
            MailLog::create([
                'unique_id' => $uniqueId,
                'status' => 'sending',
                'email' => $emails,
                'subject' => $subject,
                'body' => $body,
                'data' => json_encode($event->data),
            ]);
        }

        if ($event->message instanceof MessageSent && !empty($uniqueId)) {
            MailLog::where('unique_id', $uniqueId)->update(['status' => 'sent']);
        }

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
