<?php
$url = "http://localhost/Qloudflow-whatsapp-manager/laravel-whatsapp-manager/public/api/webhooks/whatsapp/incoming";
$data = [
    "event" => "message.received",
    "messageId" => "SIMULATED_TEST_" . time(),
    "from" => "919699867990@s.whatsapp.net",
    "phone" => "919699867990",
    "sender_jid" => "919699867990@s.whatsapp.net",
    "pushName" => "Simulator",
    "messageType" => "text",
    "message" => "Hello from simulator",
    "timestamp" => date('c'),
    "isGroup" => false,
    "groupId" => null
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpcode\n";
echo "Response: $response\n";
