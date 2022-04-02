<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof ClientException) {
            return $this->handleClientException($exception, $request);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle correctly the exceptions when sending requests
     */
    protected function handleClientException($exception, $request)
    {
        $code = $exception->getCode();

        $response = json_decode($exception->getResponse()->getBody()->getContents());//response contents
        $errorMessage = $response->error;//from the response obtain the error message

        switch ($code) {
            //handling authentication errors when consuming the API

            //The most important exception that we have to handle in our HTTP client or any suitable client is the eventual
            //case the failure of authenticating an HTTP request
            // normally because the access token failed, the access token expires, the owning system revoked the access token to  our client, etc
            case Response::HTTP_UNAUTHORIZED:

                //The first one is that the user is unauthenticated.
                //That means that the user's access token is not valid.
                //So we need to invalidate the session, removing the access tokens that we have stored there and obligate
                //the client to use the client credentials again.
                //And after closing the user's session, obligate the user to start a session again, basically authorize us again.

                //But there is another possible action.
                //That is what happen if for some reason we got revoked directly from the API .
                //For example, our client credentials are not valid anymore.
                //So in that case, any kind of possible request is going to fail because our credentials are invalid
                //we need to immediately resolve that manually changing or updating our client.

                $request->session()->invalidate();//So in both cases, we first need to invalidate the solution.

                if ($request->user()) {
                    Auth::logout();//if we have a user authenticated, we need to immediately log out this user.

                    return redirect()
                        ->route('welcome')
                        ->withErrors(['message' => 'The authentication failed. Please login again.']);
                }

                abort(500, 'Error authenticating the request. Try again later.');

            default:
                return redirect()->back()->withErrors(['message' => $errorMessage]);
        }
    }
}
