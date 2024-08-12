<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Laravel\Sanctum\HasApiTokens;

use App\Models\User;
use App\Models\Video;
use App\Models\Playlist;
use App\Models\Relationship;

use Illuminate\Support\Facades\Mail;

use Carbon\Carbon;

use Mailtrap\Config;
use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Mailtrap\EmailHeader\CategoryHeader;

use Http;

use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use HasApiTokens;

    // RETURN MEMBERSHIP STATUS
    public function membershipStatus()
    {
        $user = Auth::user();

        return response()->json(["data" => $user->membership], 200);
    }

    // VERIFY CAPTCHA TOKEN
    private function verifyCaptcha($token)
    {
        if ($token == \Config::get('captcha.placeholder_token')) {
            return true;
        }

        // Cloudflare Turnstile verification URL
        $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

        // Prepare the data for the POST request
        $data = [
            'secret' => \Config::get('captcha.secret_key'),
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
        // VALIDATE FORM FIELDS
        $request->validate([
            'username' => 'required|string|max:18|min:3',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'token' => "required|string"
        ]);

        // VERIFY CAPTCHA TOKEN
        if (!$this->verifyCaptcha($request->input('token'))) {
            return response()->json(['error' => 'Invalid captcha response'], 422);
        }
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

        // CREATE DEFAULT VIDEO FOR USER
        Video::create([
            "username" => $request->username,
            "url" => "qsOUv9EzKsg",
            "start" => 0,
            "end" => 36000,
            "skip" => serialize([]),
            "title" => "Campfire & River Night Ambience 10 Hours | Nature White Noise for Sleep, Studying or Relaxation",
            "desc" => "Discover the perfect sleep environment with this 10-hour nature white noise video",
            "lyrics" => "",
            "thumbnail" => "https://img.youtube.com/vi/qsOUv9EzKsg/hqdefault.jpg",
            "fav" => false,
            "fav_date" => null,
            "speed" => 1
        ]);

        // RETURN TOKEN, USERNAME, AND EMAIL
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['token' => $token, 'username' => $user->username, "email" => $user->email, "user_is_member" => $user->membership], 200);

    }
    public function login(Request $request)
    {
        try {
            // VALIDATE FORM FIELDS
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'token' => "required|string"
            ]);

            // VERIFY CAPTCHA TOKEN
            if (!$this->verifyCaptcha($request->input('token'))) {
                return response()->json(['error' => 'Invalid captcha response'], 422);
            }
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

            // AUTHENTICATE USER BASED ON PROVIDED CREDENTIALS
            if ($user && Auth::attempt(['email' => $user->email, 'password' => $request->input('password')])) {
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



    // ACCOUNT DELETION
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        // DELETE ALL VIDEOS
        $videos = Video::where("username", $user->username)->get();

        foreach ($videos as $v) {
            $v->delete();
        }

        // DELETE ALL PLAYLISTS
        $playlists = Playlist::where("username", $user->username)->get();

        foreach ($playlists as $p) {
            $p->delete();
        }

        // DELETE ALL RELATIONSHIPS
        $relationships = Relationship::where("username", $user->username)->get();

        foreach ($relationships as $r) {
            $r->delete();
        }

        // DELETE ACCOUNT
        $request->user()->delete();

        return response()->json(['data' => 'Account deleted successfully'], 200);
    }



    // LOGOUT
    public function logout(Request $request)
    {
        // DELETE ALL USER'S TOKENS
        Auth::user()->tokens()->delete();

        return response()->json(['data' => 'Logged out successfully'], 200);
    }



    // EMAIL VERIFICATION
    public function sendVerificationEmail()
    {
        $user = Auth::user();

        // CHECK IF USER IS ALREADY VERIFIED
        if ($user->isUserVerified()) {
            return response()->json(['data' => 'Email already verified'], 400);
        }

        // IF VERIFICATION EMAIL ALREADY SENT, CHECK HOW LONG IT HAS BEEN SINCE THE LAST SENT EMAIL
        if ($user->email_verification_token_sent_at !== null) {
            $latest_mail = Carbon::parse($user->email_verification_token_sent_at)->timestamp;

            if ($latest_mail !== null && (time() - $latest_mail) < 60) {
                return response()->json(['data' => 'Wait before requesting another verification email'], 400);
            }
        }

        $token = uuid_create();
        $user_db = User::where("username", $user->username)->first();

        // ATTEMPT TO UPDATE THE VERIFICATION TOKEN
        try {
            $user_db->update(['email_verification_token' => $token, "email_verification_token_sent_at" => Carbon::now()]);
        } catch (\Exception $e) {
            return response()->json(['data' => 'Failed to update verification token'], 500);
        }

        $body = '
        Please press the link below to verify your email address:
        
        https://repeatbeats.com/verify-email?tk=' . $token . '
        
        Note: This link will expire in 24 hours.
        If this wasn\'t you, feel free to ignore this email.
        ';

        // ATTEMPT TO SEND THE VERIFICATION EMAIL
        try {
            $apiKey = env("MAILTRAP_API_KEY");
            $mailtrap = new MailtrapClient(new Config($apiKey));

            $email = (new Email())
                ->from(new Address('mailtrap@repeatbeats.com', 'RepeatBeats'))
                ->to(new Address($user_db->email, 'RepeatBeats'))
                ->subject('RepeatBeats - Verify Account')
                ->text($body)
            ;

            $email->getHeaders()
                ->add(new CategoryHeader('Verify Email'))
            ;

            $response = $mailtrap->sending()->emails()->send($email);

            /*Mail::raw($body, function ($message) use ($user_db) {
                $message->to($user_db->email)->subject('Verify your email address')->from("info@demomailtrap.com");
            });*/
        } catch (\Exception $e) {
            return response()->json(['data' => substr($e->getMessage(), 0, 250)], 500);
        }

        // RETURN SUCCESSFUL RESPONSE
        try {
            $user_db->update(['email_verification_token_sent_at' => Carbon::now()]);
        } catch (\Exception $e) {
            return response()->json(['data' => substr($e->getMessage(), 0, 250)], 500);
        }

        return response()->json(['data' => 'Email sent successfully'], 204);
    }

    public function verifyToken(Request $request)
    {
        // VERIFY THAT TOKEN EXISTS
        $request->validate([
            "token" => "required|string|exists:users,email_verification_token"
        ]);

        $user = Auth::user();

        // VERIFY THAT AUTHENTICATED USER IS THE ONE WITH THE PROVIDED TOKEN
        $user_db = User::where("username", $user->username)->where("email_verification_token", $request->token)->first();

        // CHECK IF TOKEN EXPIRED (> 24 HOURS)
        if ($user_db->email_verification_token_sent_at !== null) {
            $token_send_date = Carbon::parse(User::where("username", $user->username)->value("email_verification_token_sent_at"));
            $now = Carbon::now();

            if ($token_send_date->diffInHours($now) >= 25) {
                return response()->json(['data' => 'This email verification token has expired'], 400);
            }
        }

        // VALIDATE THE TOKEN AND UPDATE THE USER'S STATUS
        if ($user->email_verification_token == $request->token && !$user->isUserVerified()) {
            $user->update(['email_verified' => true, 'email_verification_token' => null]);

            return response()->json(['data' => 'Email verified successfully'], 200);
        }

        return response()->json(['data' => 'Invalid verification token or email already verified'], 400);
    }

    public function isVerified()
    {
        // RETURN TRUE IF USER'S EMAIL IS VERIFIED, FALSE OTHERWISE
        $user = Auth::user();
        $user_db = User::where("username", $user->username)->first();

        return response()->json(['data' => $user_db->isUserVerified()], 200);
    }




    // CHANGE PASSWORD
    public function changePassword(Request $request)
    {
        // VALIDATE THAT PASSWORD IS GREATER THAN 8 CHARS & TOKEN EXISTS
        $request->validate([
            "token" => "required|string|exists:users,reset_password_token",
            "new_password" => "required|string|min:8"
        ]);

        // GET USER BASED ON PROVIDED TOKEN
        $user = User::where("reset_password_token", $request->token)->first();

        // IF NO USER IS FOUND WITH TOKEN, RETURN ERROR
        if (!$user) {
            return response()->json(["data" => "Invalid token"], 400);
        }

        // CHECK IF USER'S RESET PASSWORD TOKEN EXPIRED (> 24 HOURS)
        if (User::where("username", $user->username)->value("reset_password_token_sent_at") !== null) {
            $token_send_date = Carbon::parse(User::where("username", $user->username)->value("reset_password_token_sent_at"));
            $now = Carbon::now();

            if ($token_send_date->diffInHours($now) >= 25) {
                return response()->json(["data" => "Token expired"], 400);
            }
        }

        // UPDATE USER'S PASSWORD AND RESET RESET PASSWORD TOKEN
        try {
            User::where("username", $user->username)->update(["password" => Hash::make($request->new_password)]);
        } catch (\Exception $e) {
            return response()->json(["data" => $e->getMessage()], 400);
        }

        // LOG PASSWORD CHANGE
        Log::info("Password changed for user: " . $user->username);

        // SET PASSWORD RESET TOKEN & RELATED FIELDS TO NULL
        $user->update(["reset_password_token" => null, "reset_password_token_sent_at" => null]);

        return response()->json(["data" => "Password changed successfully"], 200);
    }

    public function sendResetPasswordEmail(Request $request)
    {
        // VALIDATE THAT USERNAME HAS BEEN PROVIDED
        $request->validate([
            "username" => "required|string"
        ]);

        // CHECK IF USER HAS PROVIDED USERNAME
        $user = User::where("username", $request->username)->first();

        // CHECK IF USER HAS PROVIDED EMAIL
        if (!$user) {
            $user = User::where("email", $request->username)->first();
        }

        // IF NO USER IS FOUND WITH PROVIDED EMAIL AND USERNAME, RETURN ERROR
        if (!$user) {
            return response()->json(["data" => "User not found"], 400);
        }

        $email = $user->email;

        // CHECK IF AT LEAST 60 SECONDS HAVE PASSED SINCE THE LAST SENT EMAIL
        if ($user->reset_password_token_sent_at !== null) {
            $latest_mail = Carbon::parse($user->reset_password_token_sent_at);
            $now = Carbon::now();

            if ($latest_mail->diffInSeconds($now) <= 60) {
                return response()->json(["data" => "Wait before requesting another email"], 400);
            }
        }

        // CREATE TOKEN
        $token = uuid_create();

        // ATTEMPT TO UPDATE THE RESET PASSWORD TOKEN
        try {
            $user->update(["reset_password_token" => $token]);
        } catch (\Exception $e) {
            return response()->json(["data" => "Couldn't update database"], 400);
        }

        $body = '
        Please press the link below to change your password:
        
        https://repeatbeats.com/change-password?tk=' . $token . '
        
        Note: This link will expire in 24 hours.
        If this wasn\'t you, feel free to ignore this email.
        ';

        // ATTEMPT TO SEND THE RESET PASSWORD EMAIL
        try {
            $apiKey = env("MAILTRAP_API_KEY");
            $mailtrap = new MailtrapClient(new Config($apiKey));

            $email = (new Email())
                ->from(new Address('mailtrap@repeatbeats.com', 'RepeatBeats'))
                ->to(new Address($email, 'RepeatBeats'))
                ->subject('RepeatBeats - Forgot Password')
                ->text($body)
            ;

            $email->getHeaders()
                ->add(new CategoryHeader('Forgot Password'))
            ;

            $response = $mailtrap->sending()->emails()->send($email);
        } catch (\Exception $e) {
            return response()->json(["data" => "Failed to send email - $e"], 400);
        }

        // UPDATE RESET PASSWORD TOKEN SENT AT
        $user->update(["reset_password_token_sent_at" => Carbon::now()]);

        return response()->json(["data" => "Email sent successfully"], 200);
    }




    // CHANGE EMAIL
    public function sendEmailChangeEmail(Request $request)
    {
        // VALIDATE THAT EMAIL EXISTS
        $request->validate([
            "email" => "required|string|email|max:255|unique:users,email"
        ]);

        $user = Auth::user();
        $user_db = User::where("username", $user->username)->first();

        // CHECK IF AT LEAST 60 SECONDS HAVE PASSED SINCE THE LAST EMAIL HAS BEEN SENT
        if ($user_db->email_change_token_sent_at !== null) {
            $latest_mail = Carbon::parse($user_db->email_change_token_sent_at);
            $now = Carbon::now();

            if ($latest_mail->diffInSeconds($now) <= 60) {
                return response()->json(["data" => "Wait before requesting another email"], 400);
            }
        }

        // CREATE TOKEN
        $token = uuid_create();

        // ATTEMPT TO UPDATE THE EMAIL CHANGE TOKEN
        try {
            $user_db->update(["email_change_token" => $token]);
        } catch (\Exception $e) {
            return response()->json(["data" => $e->getMessage()], 400);
        }

        // GET USER'S DESIRED EMAIL
        $email = $request->email;

        $body = '
        This email was sent because you requested to change your email address.
        Please press the link below to confirm the change:
        
        https://repeatbeats.com/change-email?tk=' . $token . '
        
        Note: This link will expire in 24 hours.
        If this wasn\'t you, you can ignore this email - someone may have put in your email address by mistake.
        ';

        // ATTEMPT TO SEND TOKEN TO DESIRED EMAIL
        try {
            $apiKey = env("MAILTRAP_API_KEY");
            $mailtrap = new MailtrapClient(new Config($apiKey));

            $email = (new Email())
                ->from(new Address('mailtrap@repeatbeats.com', 'RepeatBeats'))
                ->to(new Address($email, 'RepeatBeats'))
                ->subject('RepeatBeats - Change Email')
                ->text($body)
            ;

            $email->getHeaders()
                ->add(new CategoryHeader('Change Email'))
            ;

            $response = $mailtrap->sending()->emails()->send($email);
        } catch (\Exception $e) {
            return response()->json(["data" => "Failed to send email - $e"], 400);
        }

        // UPDATE EMAIL CHANGE TOKEN SENT AT & NEW EMAIL
        $user_db->update(["email_change_token_sent_at" => Carbon::now(), "new_email" => $request->email]);

        return response()->json(["data" => "Email sent successfully"], 200);
    }
    function changeEmail(Request $request)
    {
        // VALIDATE THAT TOKEN EXISTS
        $request->validate([
            "token" => "required|string|exists:users,email_change_token"
        ]);

        $user = Auth::user();
        // VALIDATE THAT PROVIDED TOKEN BELONGS TO THE AUTHENTICATED USER
        $user_db = User::where("username", $user->username)->where("email_change_token", $request->token)->firstOrFail();

        // GET DESIRED EMAIL
        $new_email = $user_db->new_email;

        // UPDATE USER'S EMAIL
        $user_db->update(["email" => $new_email, "email_change_token" => null, "email_change_token_sent_at" => null, "new_email" => null, "email_verified" => true]);

        return response()->json([
            "data" => $new_email
        ], 200);
    }
}
