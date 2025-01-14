<?php

namespace App\Http\Controllers\Auth;

use App\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\MarketService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\ClientException;
use App\Services\MarketAuthenticationService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * The service to authenticate actions
     *
     * @var App\Services\MarketAuthenticationService
     */
    protected $marketAuthenticationService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(MarketAuthenticationService $marketAuthenticationService, MarketService $marketService)
    {
        $this->middleware('guest')->except('logout');

        $this->marketAuthenticationService = $marketAuthenticationService;

        parent::__construct($marketService);
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        $authorizationUrl = $this->marketAuthenticationService->generateAuthorizationUrl();

        return view('auth.login')
            ->with(['authorizationUrl' => $authorizationUrl]);
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        try {
            $tokenData = $this->marketAuthenticationService->getPasswordToken($request->email, $request->password);

            $userData = $this->marketService->getUserInformation();

            $user = $this->registerOrUpdateUser($userData, $tokenData);

            $this->loginUser($user, $request->has('remember'));

            return redirect()->intended('home');

        } catch (ClientException $e) {

            $message = $e->getResponse()->getBody();

            if (Str::contains($message, 'invalid_credentials')) {
                // If the login attempt was unsuccessful we will increment the number of attempts
                // to login and redirect the user back to the login form. Of course, when this
                // user surpasses their maximum number of attempts they will get locked out.
                $this->incrementLoginAttempts($request);

                return $this->sendFailedLoginResponse($request);
            }

            throw $e;

        }


    }

    /**
     * Resolve the user authorization
     *
     * @return \Illuminate\Http\Response
     */
    public function authorization(Request $request)
    {
        //If the user clicks "Allow," the service redirects the user back to your site with an authorization code
        //If the user approves the client they will be redirected from the authorization server back to the client (specifically to the redirect URI) with the following parameters in the query string:
        //code: with the authorization code
        //state: with the state parameter sent in the original request. You should compare this value with the value stored in the user’s session to ensure the authorization code obtained is in response to requests made by this client rather than another client application.

        if ($request->has('code')) {//The server returns the authorization code in the query string
            $tokenData = $this->marketAuthenticationService->getCodeToken($request->code);

            $userData = $this->marketService->getUserInformation();
            /*
             * identifier: 1006
             * name: user2
             * email: user2@users.com
             * isVerified: 0
             * isAdmin: false
             * creationDate: 2019-04-10 21:40
             * lastChange: 2019-04-10 21:40
             * deletedDate: null
             * */

            //registering or updating users with information from the API
            $user = $this->registerOrUpdateUser($userData, $tokenData);

            //creating sessions for users validated by the HTTP service
            $this->loginUser($user);

            return redirect()->intended('home');
        }

        return redirect()
            ->route('login')
            ->withErrors(['You canecelled the authorization process']);
    }

    /**
     * Create or update a user using information from the API
     */
    public function registerOrUpdateUser($userData, $tokenData)
    {
        return User::updateOrCreate(
            [
                'service_id' => $userData->identifier,
            ],
            [
                'grant_type' => $tokenData->grant_type,
                'access_token' => $tokenData->access_token,
                'refresh_token' => $tokenData->refresh_token,
                'token_expires_at' => $tokenData->token_expires_at,
            ]
        );
    }

    /**
     * Create a user session in the HTTP Client
     * @return void
     */
    public function loginUser(User $user, $remember = true)
    {
        Auth::login($user, $remember);

        session()->regenerate();
    }
}
