<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;

use Auth;
use App\Models\User;

class PaymentController extends Controller
{
    public function createPaymentIntent()
    {

        $user = Auth::user();

        if ($user->membership == "active") {
            return response()->json(['message' => 'You\'ve already upgraded your account'], 400);
        }

        try {
            Stripe::setApiKey(env('STRIPE_SECRET', 'sk_test_51PkU3eKwy6XznOs2m171ssPfJju4ztCrCKJAnwdBt6oFP1Qyx6shYTUskQX0HXqMlyIQ7hIlmrSfcY96eBMzayft00Vx8aMBK6'));

            try {
                $paymentIntent = PaymentIntent::create([
                    'amount' => 30,
                    'currency' => 'gbp',
                ]);

                return response()->json([
                    'clientSecret' => $paymentIntent->client_secret,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateMembership($user) {
        $user->membership_date = now();
        $user->membership = true;

        $user->save();
    }

    public function upgrade(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET', 'sk_test_51PkU3eKwy6XznOs2m171ssPfJju4ztCrCKJAnwdBt6oFP1Qyx6shYTUskQX0HXqMlyIQ7hIlmrSfcY96eBMzayft00Vx8aMBK6'));
    
        try {
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);
    
            if ($paymentIntent->status == 'succeeded') {
                $user = Auth::user();
                
                $this->updateMembership($user);
    
                return response()->json(['message' => 'You\'ve successfully upgraded your account!']);
            } else {
                // Confirm the payment intent if it is not already confirmed
                $paymentIntent->confirm();
    
                if ($paymentIntent->status == 'succeeded') {
                    $user = Auth::user();
                    
                    $this->updateMembership($user);
    
                    return response()->json(['message' => 'You\'ve successfully upgraded your account!']);
                } else {
                    return response()->json(['error' => 'Payment could not be completed'], 400);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}