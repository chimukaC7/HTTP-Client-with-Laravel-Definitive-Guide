<?php

namespace App\Traits;

trait Responses
{
    /**
     * Decode correspondingly the response
     */
    public function decodeResponse($response)
    {
        $decodedResponse = json_decode($response);

        return $decodedResponse->data ?? $decodedResponse;
    }

    /**
     * Resolve when the request failed
     */
    public function checkIfErrorResponse($response)
    {
        if (isset($response->error)) {//if the response contains an error element inside it
            throw new \Exception("Something failed: {$response->error}");
        }
    }

}
