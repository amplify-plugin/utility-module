<?php

namespace Amplify\System\Utility\Listeners;

use Amplify\System\Utility\Models\ApiLog;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;

class ApiLogListener
{
    protected ApiLog $apiLog;

    protected Request $request;

    protected $response;

    /**
     * Create the event listener.
     */
    public function __construct(ApiLog $apiLog)
    {
        $this->apiLog = $apiLog;
    }

    /**
     * Handle the event.
     *
     * @param  ResponseReceived|ConnectionFailed  $event
     * @return void
     */
    public function handle($event)
    {
        $this->request = $event->request;
        $this->response = $event->response ?? null;

        $api_host = $this->request->toPsrRequest()->getUri()->getHost();

        // Search Logger
        $search_config = config('amplify.search.easyask_host');
        if ($api_host == $search_config) {
            if (! config('amplify.search.logger_enabled')) {
                return;
            }
        }

        // Report Logger
        $report_config = config('amplify.report.host');
        if ($api_host == $report_config) {
            if (! config('amplify.report.logger_enabled')) {
                return;
            }
        }

        // Payment Logger
        $payment_config = config('amplify.payment.gateways.'.config('amplify.payment.default').'.payment_url');
        $payment_config = $this->getHostFromUri($payment_config);
        if ($api_host == $payment_config) {
            if (! config('amplify.payment.logger_enabled')) {
                return;
            }
        }

        // ERP Logger
        $erp_config = config('amplify.erp.configurations.'.config('amplify.erp.default').'.url');
        $erp_config = $this->getHostFromUri($erp_config);
        if ($api_host == $erp_config) {
            if (! config('amplify.erp.logger_enabled')) {
                return;
            }
        }

        $this->apiLog->group = $this->request->toPsrRequest()->getUri()->getHost();
        $this->apiLog->method = $this->request->method();
        $this->apiLog->url = $this->request->url();
        $this->apiLog->type = $this->request->header('Content-Type')[0] ?? 'application/x-www-form-urlencoded';
        $this->apiLog->request_header = $this->request->headers();
        $this->apiLog->request_body = $this->request->data();

        if ($this->request->method() == 'GET') {
            parse_str(parse_url($this->request->url(), PHP_URL_QUERY), $queryParams);
            $this->apiLog->request_body = $queryParams;
        }

        if ($event instanceof ResponseReceived) {
            $this->apiLog->status_code = $this->response->status();
            $this->apiLog->status_text = $this->response->reason();

            $this->apiLog->response_time = $this->response->handlerStats()['total_time_us'] ?? 0;
            if ($this->apiLog->response_time > 0) {
                $this->apiLog->response_time = $this->apiLog->response_time / 1000000.0;
            }

            $this->apiLog->response_header = $this->response->headers();
            $this->apiLog->response_body = $this->response->body();
        }

        if ($event instanceof ConnectionFailed) {
            $this->apiLog->status_code = 500;
            $this->apiLog->status_text = 'Connection Failed';
            $this->apiLog->response_time = 0;
            $this->apiLog->response_header = [];
            $this->apiLog->response_body = [];
        }

        $this->apiLog->save();
    }

    private function getHostFromUri(?string $uri = null)
    {
        return parse_url($uri, PHP_URL_HOST);

    }
}
