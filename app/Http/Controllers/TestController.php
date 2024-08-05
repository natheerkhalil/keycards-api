<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestEmail;

class TestController extends Controller
{
    public function testEmail()
    {
        $data = [];

        try {
            // Use the correct view reference
            /*Mail::send('emails.test', $data, function ($message) {
                $message->to('natheerdev@outlook.com')->subject('Test Email');
            });*/
            Mail::raw('Hi, welcome user!', function ($message) {
                $message->to('natheerdev@outlook.com')->subject('Test Email')->from("info@demomailtrap.com");
            });

            return response()->json(['status' => 'Email sent successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
            return response()->json(['status' => "Failed to send email, see logs for details - " . substr($e->getMessage(), 0, 250)], 500);
        }
    }
}