<?php

namespace Modules\Support\Services;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SMSService
{

    public static function sendOTP($phone, $otp): PromiseInterface|Response
    {
        $url = 'https://www.msegat.com/gw/sendsms.php';

        $message = "";
        if (app()->getLocale() == 'ar') {
            $message = "رمز التحقق: $otp";
        } else {
            $message = "Verification Code: $otp";
        }

        // Define the payload for the request
        $payload = [
            "userName" => "nasser7687",
            "numbers" => $phone,
            "apiKey" => "9419D2F0F5D1244AA83BBE72E1688519",
            "userSender" => "Khyyal",

            "msg" => $message,
            "msgEncoding" => "UTF8"
        ];

        $response = Http::post($url, $payload);
        return $response;

    }


}
