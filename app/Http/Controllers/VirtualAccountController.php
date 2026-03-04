<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class VirtualAccountController extends Controller
{
    public function createVirtualAccount(Request $request)
    {
        try {
            Log::info('=== CREATE VA REQUEST ===', $request->all());

            $this->validate($request, [
                'amount'          => 'required|numeric|min:10000',
                'bank_code'       => 'required|string|in:BCA,BRI,BNI,MANDIRI,PERMATA,CIMB',
                'customer.full_name' => 'required|string|max:255',
                'customer.email'   => 'required|email|max:255',
                'customer.phone'   => 'required|string|max:20',
                'external_id'      => 'required|string'
            ]);

            $secretKey = env('XENDIT_SECRET_KEY');
            if (empty($secretKey)) {
                throw new \Exception('XENDIT_SECRET_KEY not set');
            }

            $client = new Client([
                'base_uri' => 'https://api.xendit.co',
                'auth' => [$secretKey, ''],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json'
                ],
                'http_errors' => false // agar kita bisa menangani sendiri error HTTP
            ]);

            $payload = [
                'external_id'     => $request->external_id,
                'bank_code'       => $request->bank_code,
                'name'            => $request->customer['full_name'],
                'expected_amount' => (float) $request->amount,
                'is_closed'       => true,
                'expiration_date' => date('c', strtotime('+1 day')),
                'is_single_use'   => true,
                'currency'        => 'IDR'
            ];

            Log::info('Sending to Xendit VA:', $payload);

            // Coba dengan endpoint v2
            $response = $client->post('/v2/virtual_accounts', [
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            Log::info('Xendit VA response status: ' . $statusCode);
            Log::info('Xendit VA response body: ' . $body);

            if ($statusCode >= 200 && $statusCode < 300) {
                $body = json_decode($body, true);
                return response()->json([
                    'status'         => 'success',
                    'message'        => 'Virtual Account berhasil dibuat',
                    'account_number' => $body['account_number'],
                    'bank_code'      => $body['bank_code'],
                    'amount'         => $request->amount,
                    'external_id'    => $body['external_id'],
                    'expiration_date'=> $body['expiration_date']
                ]);
            } else {
                // Coba endpoint tanpa v2
                Log::info('Trying endpoint without /v2');
                $response = $client->post('/virtual_accounts', [
                    'json' => $payload
                ]);
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                Log::info('Xendit VA (legacy) response status: ' . $statusCode);
                Log::info('Xendit VA (legacy) response body: ' . $body);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $body = json_decode($body, true);
                    return response()->json([
                        'status'         => 'success',
                        'message'        => 'Virtual Account berhasil dibuat (legacy)',
                        'account_number' => $body['account_number'],
                        'bank_code'      => $body['bank_code'],
                        'amount'         => $request->amount,
                        'external_id'    => $body['external_id'],
                        'expiration_date'=> $body['expiration_date']
                    ]);
                } else {
                    throw new \Exception('Xendit error: ' . $body);
                }
            }

        } catch (\Exception $e) {
            Log::error('VA Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat Virtual Account: ' . $e->getMessage()
            ], 500);
        }
    }
}