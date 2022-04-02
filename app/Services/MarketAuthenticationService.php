<?php

namespace App\Services;

use App\Traits\ConsumesExternalServices;
use App\Traits\Responses;

class MarketAuthenticationService
{
    use ConsumesExternalServices, Responses;

    /*
     * describes a number of grants (“methods”) for a client application to acquire an access token
     * (which represents a user’s permission for the client to access their data) which can be used to authenticate a request to an API endpoint.
     *
     * The specification describes five grants for acquiring an access token:
            -Authorization code grant -for apps running on a web server, browser-based and mobile apps
            -Resource owner credentials grant/Password -for logging in with a username and password (only for first-party apps)
            -Client credentials grant -for application access without a user present
            -Refresh token grant
     * */

    /*
     * Creating an App
        -Before you can begin the OAuth process, you must first register a new app with the service.
        -When registering a new app, you usually register basic information such as
            application name,
            website,
            a logo, etc.
        -In addition, you must register a redirect URI to be used for redirecting users to for web server, browser-based, or mobile apps.
     * Client ID and Secret
        -After registering your app, you will receive a client ID and optionally a client secret.
        -The client ID is considered public information, and is used to build login URLs, or included in Javascript source code on a page.
        -The client secret must be kept confidential
    */

    /*
     * Client - An application making protected resource requests on behalf of the resource owner and with its authorization. The term client does not imply any particular implementation characteristics (e.g. whether the application executes on a server, a desktop, or other devices).
     * Authorization server - The server issuing access tokens to the client after successfully authenticating the resource owner and obtaining authorization
     * Security: Note that the service must require apps to pre-register their redirect URIs.
     * */

    /*
     * Making Authenticated Requests
        -The end result of all the grant types is obtaining an access token.
        -Now that you have an access token, you can make requests to the API.

        -Make sure you always send requests over HTTPS and never ignore invalid certificates. HTTPS is the only thing protecting requests from being intercepted or modified
     * */


    //the URL to send the requests
    protected $baseUri;

    //The client id to identify the client in the API
    //The client ID you received when you first created the application
    protected $clientId;

    //The client secret to identify the client in the API
    protected $clientSecret;

    //The client id to identify the password client in the API
    protected $passwordClientId;

    //The client secret to identify the password client in the API
    protected $passwordClientSecret;

    public function __construct()
    {
        $this->baseUri = config('services.market.base_uri');
        $this->clientId = config('services.market.client_id');
        $this->clientSecret = config('services.market.client_secret');
        $this->passwordClientId = config('services.market.password_client_id');
        $this->passwordClientSecret = config('services.market.password_client_secret');
    }

    //CLIENT CREDENTIALS GRANT------------------------------------------------------------------------------------------
    //this grant is suitable for machine-to-machine authentication where a specific user’s permission to access data is not required.
    //In some cases, applications may need an access token to act on behalf of themselves rather than a user.
    // -For example, the service may provide a way for the application to update their own information such as their website URL or icon, or they may wish to get statistics about the users of the app.
    // -In this case, applications need a way to get an access token for their own account, outside the context of any specific user

    //Obtains an access token associated with the client
    public function getClientCredentialsToken()
    {
        if ($token = $this->existingValidToken())  {
            return $token;
        }

        //The Flow
        //The client sends a POST request with following body parameters to the authorization server:

        $formParams = [
            'grant_type' => 'client_credentials',//with the value client_credentials
            'client_id' => $this->clientId,//with the client’s ID
            'client_secret' => $this->clientSecret,//with the client’s secret
            //scope with a space-delimited list of requested scope permissions.
        ];

        $tokenData = $this->makeRequest('POST', 'oauth/token', [], $formParams);//send request to the authorization server and save response
        //The authorization server will respond with a JSON object containing the following properties:
        //token_type: with the value Bearer
        //expires_in: with an integer representing the TTL of the access token
        //access_token: the access token itself

        $this->storeValidToken($tokenData, 'client_credentials');

        return $tokenData->access_token;
    }
    //END OF CLIENT CREDENTIALS GRANT-----------------------------------------------------------------------------------

    //we are going to start implementing all what we need to authenticate users
    //into our client using the information provided by the API.

    //AUTHORISATION CODE GRANT------------------------------------------------------------------------------------------
    //Generate the URL to obtain users authorisation
    public function generateAuthorizationUrl(): string
    {
        //The Flow (Part One)
        //Create a "Log In" link sending the user to: https://authorization-server.com/auth?response_type=code&client_id=CLIENT_ID&redirect_uri=REDIRECT_URI&scope=photos&state=1234zyx
        //The client will redirect the user to the authorization server with the following parameters in the query string:
        //The user sees the authorization prompt
        //All of these parameters will be validated by the authorization server.
        $query = http_build_query([
            'client_id' => $this->clientId,//with the client identifier (The client ID you received when you first created the application)
            'redirect_uri' => route('authorization'),//with the client redirect URI.
            'response_type' => 'code',//with the value code (Indicates that your server expects to receive an authorization code)
            'scope' => 'purchase-product manage-products manage-account read-general',// a space delimited list of scopes
            //state - A random string generated by your application, which you'll verify later
        ]);

        return "{$this->baseUri}/oauth/authorize?{$query}";

        //The user will then be asked to login to the authorization server and approve the client
        //If the user approves the client they will be redirected from the authorization server back to the client (specifically to the redirect URI)
    }

    //Obtains an access token from a given code
    public function getCodeToken($code)
    {

        //The Flow (Part Two)
        //The client will now send a POST request to the authorization server with the following parameters:
        $formParams = [
            'grant_type' => 'authorization_code',//with the value of authorization_code
            'client_id' => $this->clientId,//the client identifier (The client ID you received when you first created the application)
            'client_secret' => $this->clientSecret,//with the client secret
            'redirect_uri' => route('authorization'),//with the same redirect URI the user was redirect back to
            'code' => $code,//with the authorization code from the query string (This is the code you received in the query string)
        ];

        //your client exchanges the authorization code for an access token by making a POST request to the authorization server's token endpoint:
        $tokenData = $this->makeRequest('POST', 'oauth/token', [], $formParams);
        //The authorization server will respond with a JSON object containing the following properties:
        /*
         * token_type: Bearer (this will usually be the word “Bearer” (to indicate a bearer token))
         * expires_in: 300 (when the token will expire)
         * access_token: Bearer xxxxxxxxxxxxxx (the access token itself)
         * refresh_token: xxxxxxxxxxxxxxxxxxxx (a refresh token that can be used to acquire a new access token when the original expires)
         * token_expires_at: xxxxxxxxxxxxxxxxx
         * grant_type: authorization_code
         * */

        $this->storeValidToken($tokenData, 'authorization_code');

        return $tokenData;
    }
    //END OF AUTHORISATION CODE GRANT-----------------------------------------------------------------------------------

    //PASSWORD GRANT----------------------------------------------------------------------------------------------------
    // Obtains an access token the user credentials/Resource owner credentials grant
    public function getPasswordToken($username, $password)
    {
        //This grant type can be used to exchange a username and password for an access token directly

        //The Flow
        //The client will ask the user for their authorization credentials (usually a username and password).
        //Since this obviously requires the application to collect the user's password, it must only be used by apps created by the service itself
        //The client then sends a POST request with following body parameters to the authorization server:
        //Note, the client secret may not be included here under the assumption that most of the use cases for password grants will be mobile or desktop apps, where the secret cannot be protected.
        $formParams = [
            'grant_type' => 'password',//with the value password
            'client_id' => $this->passwordClientId,//with the client’s ID
            'client_secret' => $this->passwordClientSecret,//with the client’s secret
            'username' => $username,//with the user’s username (The user's username as collected by the application)
            'password' => $password,//with the user’s password (The user's password as collected by the application)
            'scope' => 'purchase-product manage-products manage-account read-general',// with a space-delimited list of requested scope permissions.
        ];

        $tokenData = $this->makeRequest('POST', 'oauth/token', [], $formParams);
        //The authorization server will respond with a JSON object containing the following properties:
        //token_type: with the value Bearer
        //expires_in: with an integer representing the TTL of the access token
        //access_token: the access token itself
        //refresh_token: a refresh token that can be used to acquire a new access token when the original expires

        $this->storeValidToken($tokenData, 'password');

        return $tokenData;
    }
    //END OF PASSWORD GRANT---------------------------------------------------------------------------------------------

    /**
     * Obtains an access token from the current user
     * @return string
     */
    public function getAuthenticatedUserToken()
    {
        $user = auth()->user();

        if (now()->lt($user->token_expires_at)) {//current time is less than the expiration time of the token
            return $user->access_token;
        }

        return $this->refreshAuthenticatedUserToken($user);

    }

    /**
     * Refresh a valid token from a User
     * @return string
     */
    public function refreshAuthenticatedUserToken($user)
    {
        $clientId = $this->clientId;
        $clientSecret = $this->clientSecret;

        if ($user->grant_type === 'password') {
            $clientId = $this->passwordClientId;
            $clientSecret = $this->passwordClientSecret;
        }

        $formParams = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $user->refresh_token,
        ];

        $tokenData = $this->makeRequest('POST', 'oauth/token', [], $formParams);

        $this->storeValidToken($tokenData, $user->grant_type);

        //save the new token
        $user->fill([
            'access_token' => $tokenData->access_token,
            'refresh_token' => $tokenData->refresh_token,
            'token_expires_at' => $tokenData->token_expires_at,
        ]);

        $user->save();

        return $tokenData->access_token;
    }

    /**
     * Stores a valid token with some attributes
     * @return void
     */
    public function storeValidToken($tokenData, $grantType)
    {
        $tokenData->token_expires_at = now()->addSeconds($tokenData->expires_in - 5);//remove 5 secs to give chance to retrieve some information before it expires
        $tokenData->access_token = "{$tokenData->token_type} {$tokenData->access_token}";
        $tokenData->grant_type = $grantType;

        session()->put(['current_token' => $tokenData]);
    }

    /**
     * Verify if there is any valid token on session
     * @return string\boolean
     */
    public function existingValidToken()
    {
        if (session()->has('current_token')) {
            $tokenData = session()->get('current_token');

            if (now()->lt($tokenData->token_expires_at)) {//if the current time is less than the token expiration time
                return $tokenData->access_token;
            }
        }

        return false;
    }
}
