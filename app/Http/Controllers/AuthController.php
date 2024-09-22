<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Laravel\Sanctum\HasApiTokens;

use App\Models\User;

use Mailtrap\Config;

use Http;

class AuthController extends Controller
{
    use HasApiTokens;

    // VERIFY CAPTCHA TOKEN
    private function verifyCaptcha($token)
    {
        if ($token == \Config::get('captcha.token')) {
            return true;
        }

        // Cloudflare Turnstile verification URL
        $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

        // Prepare the data for the POST request
        $data = [
            'secret' => \Config::get('captcha.secret'),
            'response' => $token,
        ];
        try {
            // Send the POST request to Cloudflare Turnstile
            $response = Http::asForm()->post($url, $data);

            // Decode the JSON response
            $result = $response->json();

            // Check if the captcha verification was successful
            return $result['success'];
        } catch (\Exception $e) {
            return false;
        }
    }


    // REGISTERING & LOGGING IN
    public function register(Request $request)
    {
        try {
            // VALIDATE FORM FIELDS
            $request->validate([
                'username' => 'required|string|max:18|min:3',
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:8',
               // 'token' => "required|string"
            ]);

            $unchanged_username = $request->input('username');

            // Remove all spaces and non-alphanumeric characters
            $request->username = preg_replace('/[^a-zA-Z0-9]+/', '', $request->input('username'));

            // Make username lowercase
            $request->username = strtolower($request->username);

            // Make email lowercase
            $request->email = strtolower($request->email);

            if ($request->username != $unchanged_username) {
                return response()->json(['message' => 'Username contains invalid characters'], 400);
            }

            // VERIFY CAPTCHA TOKEN
            /* if (!$this->verifyCaptcha($request->input('token'))) {
                 return response()->json(['error' => 'Invalid captcha response ' . \Config::get('captcha.token') . "|||" . $request->token], 498);
             }*/
            // END CAPTCHA VERIFICATION


            // CHECK IF USERNAME OR EMAIL ALREADY EXISTS
            if (User::where('email', $request->email)->exists()) {
                return response()->json(['message' => 'Email already exists'], 409);
            }
            if (User::where('username', $request->username)->exists()) {
                return response()->json(['message' => 'Username already exists'], 409);
            }

            // CREATE NEW USER
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            // RETURN TOKEN, USERNAME, AND EMAIL
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json(['token' => $token, 'username' => $user->username, "email" => $user->email], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            // VALIDATE FORM FIELDS
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            // 'token' => "required|string"
            ]);

            // Set username or email to lowercase
            $request->username = strtolower($request->input('username'));

            // VERIFY CAPTCHA TOKEN
            /*if (!$this->verifyCaptcha($request->input('token'))) {
                return response()->json(['error' => 'Invalid captcha response'], 498);
            }*/
            // END CAPTCHA VERIFICATION

            // VERIFY USER'S CREDENTIALS
            $input = $request["username"];
            $inpType = filter_var($input, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            // CHECK IF USER IS USING EMAIL OR USERNAME TO LOGIN
            if ($inpType == "email") {
                $user = User::where('email', $input)->first();
            } else {
                $user = User::where('username', $input)->first();
            }

            if (!$user) {
                return response()->json(['data' => 'User not found'], 401);
            }

            // AUTHENTICATE USER BASED ON PROVIDED CREDENTIALS
            if (Auth::attempt(['email' => $user->email, 'password' => $request->input('password')])) {
                $token = $user->createToken('auth_token')->plainTextToken;

                // RETURN TOKEN, USERNAME, AND EMAIL
                return response()->json(['token' => $token, 'username' => $user->username, "email" => $user->email, "user_is_member" => $user->membership], 200);
            } else {
                return response()->json(['data' => 'Invalid login credentials'], 401);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    // LOGOUT
    public function logout(Request $request)
    {
        // DELETE ALL USER'S TOKENS
        Auth::user()->tokens()->delete();

        return response()->json(['data' => 'Logged out successfully'], 200);
    }
}
