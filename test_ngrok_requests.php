<?php
$json = @file_get_contents('http://127.0.0.1:4040/api/requests/http');
if (!$json) {
    echo "Ngrok not reachable.\n";
    exit;
}

$data = json_decode($json, true);
if (empty($data['requests'])) {
    echo "No requests recorded by Ngrok.\n";
    exit;
}

echo "Recent Ngrok Requests:\n";
foreach ($data['requests'] as $req) {
    $time = date('Y-m-d H:i:s', strtotime($req['start']));
    echo "[{$time}] [{$req['request']['method']}] {$req['request']['uri']} - {$req['response']['status_code']}\n";
}
