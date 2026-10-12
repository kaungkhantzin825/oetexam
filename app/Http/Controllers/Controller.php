<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Give an AI-backed request enough headroom to finish its work.
     *
     * PHP's web SAPI caps a request at `max_execution_time` (60s on the stock
     * php.ini). The AI chain is bounded by `config('ai.total_budget_seconds')`,
     * so we lift the limit comfortably above that budget. Without this PHP kills
     * the request mid-flight and the client sees a fatal error instead of the
     * classified AiException every AI endpoint is written to return.
     */
    protected function extendExecutionTime(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('ai.total_budget_seconds', 45) + 20);
        }
    }
}
