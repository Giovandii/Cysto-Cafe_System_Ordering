/**
 * BUAT TRANSAKSI QRIS RESMI VIA MIDTRANS CORE API
 */
function buat_midtrans_qris($order_id, $gross_amount)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    $server_key = $config['midtrans_server_key'] ?? '';
    if (empty($server_key)) return false;

    $url = 'https://api.sandbox.midtrans.com/v2/charge';

    $payload = [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id'     => $order_id . '-' . substr(time(), -4),
            'gross_amount' => (int)$gross_amount
        ],
        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    $auth = base64_encode($server_key . ':');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if (!empty($json['actions'][0]['url'])) {
        return [
            'midtrans_order_id' => $json['order_id'],
            'qr_image_url'      => $json['actions'][0]['url'],
            'qr_string'         => $json['qr_string'] ?? '',
            'transaction_id'    => $json['transaction_id'] ?? ''
        ];
    }
    return false;
}