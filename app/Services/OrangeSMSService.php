<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OrangeSMSService
{
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->clientId = env('ORANGE_CLIENT_ID');
        $this->clientSecret = env('ORANGE_CLIENT_SECRET');
    }

    private function getAccessToken()
    {
        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        ])->post('https://api.orange.com/oauth/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new \Exception('Erreur lors de la récupération du token Orange.');
        }

        return $response->json()['access_token'];
    }

    public function sendSMS($phoneNumber, $message)
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post('https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests', [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:+225' . $phoneNumber,
                'senderAddress' => 'tel:+2250000',
                'outboundSMSTextMessage' => [
                    'message' => $message,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception('Erreur lors de l’envoi du SMS : ' . $response->body());
        }

        return $response->json();
    }
}
