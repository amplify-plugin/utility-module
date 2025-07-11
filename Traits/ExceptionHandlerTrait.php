<?php

namespace Amplify\System\Utility\Traits;

use Amplify\System\Utility\Mails\ExceptionReportMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

trait ExceptionHandlerTrait
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $exception) {
            if ($exception instanceof \Error) {
                $this->notifyException($exception);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not Found.'], 404);
            }
        });
    }

    /**
     * Write code on Method
     */
    private function notifyException(Throwable $throwable): void
    {
        try {
            if (app()->environment('production')) {

                $support_mails = config('mail.error_notify_email');

                if (strlen($support_mails) > 5) {

                    $support_mails = filter_var_array(explode(',', $support_mails), FILTER_VALIDATE_EMAIL);

                    if (!empty($support_mails)) {

                        $content['message'] = $throwable->getMessage();
                        $content['file'] = $throwable->getFile();
                        $content['line'] = $throwable->getLine();
                        $content['trace'] = $throwable->getTrace();
                        $content['url'] = request()->url();
                        $content['body'] = request()->all();
                        $content['ip'] = request()->ip();

                        Mail::to($support_mails)->send(new ExceptionReportMail($content));
                    }
                }
            }
        } catch (Throwable $exception) {
            logger()->error($exception);
        } finally {
            logger()->error($throwable);
        }
    }
}
