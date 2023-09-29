<?php

namespace DTApi\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    protected function __sendResponse($data = array(), $response_code = 200, $message = null)
    {
        $response = [
            'code'       => $response_code,
            'data'       => $data,
            'message'    => $message,
        ];

        return response()->json($response, $response_code);
    }
}
