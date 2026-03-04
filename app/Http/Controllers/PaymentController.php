<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Illuminate\Support\Facades\DB;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private $availableBankChannels = [
        'BCA',
        'BRI',
        'BNI',
        'MANDIRI',
        'PERMATA',
        'CIMB'
    ];

    private $availableEwalletChannels = [
        'OVO',
        'DANA',
        'GOPAY',
        'SHOPEEPAY',
        'LINKAJA'
    ];

    public function __construct()
    {
        $secretKey = env('XENDIT_SECRET_KEY');
        if (empty($secretKey)) {
            Log::error('XENDIT_SECRET_KEY is not set');
            throw new \Exception('XENDIT_SECRET_KEY is not configured');
        }
        Configuration::setXenditKey($secretKey);
        Log::info('Xendit configured successfully');
    }

    public function createDonation(Request $request)
    {
        try {
            Log::info('=== PAYMENT REQUEST START ===', $request->all());

            $this->validate($request, [
                'amount' => 'required|numeric|min:10000',
                'payment_method' => 'required|string|in:bank_transfer,ewallet,qris,retail',
                'channel' => 'required|string',
                'customer.full_name' => 'required|string|max:255',
                'customer.email' => 'required|email|max:255',
                'customer.phone' => 'required|string|max:20',
                'campaign_id' => 'nullable|integer',
                'notes' => 'nullable|string|max:500',
            ]);

            $channel = strtoupper($request->channel);
            $paymentMethod = $request->payment_method;
            $externalId = 'DON-' . time() . '-' . uniqid();

            // Tentukan payment methods berdasarkan tipe
            $paymentMethods = [];
            if ($paymentMethod === 'bank_transfer') {
                $paymentMethods = [$channel];
            } elseif ($paymentMethod === 'ewallet') {
                $paymentMethods = [$channel];
            } elseif ($paymentMethod === 'qris') {
                $paymentMethods = [$channel];
            } elseif ($paymentMethod === 'retail') {
                $paymentMethods = [$channel];
            }

            $invoiceData = [
                'external_id' => $externalId,
                'amount' => (float) $request->amount,
                'description' => 'Donasi untuk Campaign #' . ($request->campaign_id ?? 'Umum'),
                'payer_email' => $request->customer['email'],
                'payer_phone' => $request->customer['phone'],
                'payment_methods' => $paymentMethods,
                'success_redirect_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment-success',
                'failure_redirect_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment-failed',
                'metadata' => [
                    'customer_name' => $request->customer['full_name'],
                    'campaign_id' => $request->campaign_id,
                    'notes' => $request->notes,
                    'channel' => $channel,
                    'payment_method' => $paymentMethod
                ]
            ];

            // Untuk e-wallet, perlu channel_properties
            if ($paymentMethod === 'ewallet') {
                $invoiceData['channel_properties'] = [
                    'success_redirect_url' => env('APP_FRONTEND_URL', 'http://localhost:3000') . '/payment-success',
                ];
            }

            Log::info('Sending to Xendit Invoice API:', $invoiceData);

            $apiInstance = new InvoiceApi();
            $createInvoiceRequest = new CreateInvoiceRequest($invoiceData);
            $result = $apiInstance->createInvoice($createInvoiceRequest);

            Log::info('Xendit success', [
                'invoice_url' => $result->getInvoiceUrl(),
                'external_id' => $result->getExternalId()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice berhasil dibuat',
                'invoice_url' => $result->getInvoiceUrl(),
                'external_id' => $result->getExternalId(),
                'invoice_id' => $result->getId()
            ]);
        } catch (\Exception $e) {
            Log::error('=== PAYMENT ERROR ===');
            Log::error('Message: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkPaymentStatus($externalId)
    {
        try {
            $apiInstance = new InvoiceApi();
            $invoices = $apiInstance->getInvoices(null, $externalId);
            if (count($invoices) > 0) {
                $invoice = $invoices[0];
                return response()->json([
                    'status' => 'success',
                    'payment_status' => $invoice->getStatus(),
                    'invoice' => $invoice
                ]);
            }
            return response()->json(['status' => 'error', 'message' => 'Invoice tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Check status error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function handleXenditCallback(Request $request)
    {
        try {
            $data = $request->all();
            Log::info('=== XENDIT CALLBACK RECEIVED ===', $data);

            // Pastikan variabel status dan external_id ada
            $status = $data['status'] ?? null;
            $externalId = $data['external_id'] ?? null;

            if (!$externalId || !$status) {
                return response()->json(['message' => 'Invalid payload'], 400);
            }

            // Xendit mengirim status 'PAID' atau 'SETTLED' untuk pembayaran sukses
            if ($status === 'PAID' || $status === 'SETTLED') {

                // 1. Cari data donasi terlebih dahulu
                $donation = DB::table('donations')->where('transaction_id', $externalId)->first();

                if ($donation) {
                    // Cegah "Double Update" jika statusnya sudah success (berjaga-jaga jika Xendit kirim webhook 2x)
                    if ($donation->status !== 'success') {

                        // 2. Update status donasi menjadi success
                        DB::table('donations')
                            ->where('transaction_id', $externalId)
                            ->update([
                                'status' => 'success',
                                'updated_at' => Carbon::now()
                            ]);

                        Log::info("Donation $externalId successfully updated to success.");

                        // 3. UPDATE PROGRESS CAMPAIGN (Raised & Donors)
                        // Karena di tabel donations kamu menyimpan nama campaign di kolom 'campaign'
                        $campaign = \App\Models\Campaign::where('name', $donation->campaign)->first();

                        if ($campaign) {
                            $campaign->raised = ($campaign->raised ?? 0) + $donation->amount;
                            $campaign->donors = ($campaign->donors ?? 0) + 1;
                            $campaign->save();

                            Log::info("Campaign '{$campaign->name}' updated! Total Raised: {$campaign->raised}");
                        } else {
                            Log::warning("Campaign dengan nama '{$donation->campaign}' tidak ditemukan!");
                        }
                    } else {
                        Log::info("Donation $externalId is already success. Ignored.");
                    }
                } else {
                    // Jika ID DON-... tidak ditemukan di database
                    Log::warning("Donation $externalId not found in database.");
                }
            }

            return response()->json(['message' => 'Callback processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Callback Error: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function getAvailablePaymentMethods()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'bank_transfer' => $this->availableBankChannels,
                'ewallet' => $this->availableEwalletChannels,
                'qris' => ['QRIS'],
                'retail' => ['ALFAMART', 'INDOMARET']
            ]
        ]);
    }
}