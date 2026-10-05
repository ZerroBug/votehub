<?php
declare(strict_types=1);

const PAYSTACK_BASE_URL = 'https://api.paystack.co';
const PAYSTACK_CURRENCY = 'GHS';

final class PaystackException extends RuntimeException
{
    public function __construct(string $message, public readonly string $codeName = 'PAYSTACK_ERROR', public readonly int $httpStatus = 0)
    { parent::__construct($message); }
}

function paystackConfig(): array
{
    $file = __DIR__ . '/secrets.php';
    $secrets = is_file($file) ? require $file : [];
    if (!is_array($secrets)) $secrets = [];
    $key = trim((string)(getenv('PAYSTACK_SECRET_KEY') ?: ($secrets['paystack_secret_key'] ?? '')));
    $mode = strtolower(trim((string)($secrets['paystack_mode'] ?? '')));
    if ($mode === '') $mode = str_starts_with($key, 'sk_live_') ? 'live' : 'test';
    return ['mode'=>$mode, 'key'=>$key];
}

function paystackSecretKey(): string { return paystackConfig()['key']; }
function paystackMode(): string { return paystackConfig()['mode']; }

function paystackRequest(string $method, string $endpoint, ?array $payload = null): array
{
    $cfg = paystackConfig();
    $secret = $cfg['key'];
    if ($secret === '') throw new PaystackException('Paystack is not configured. Add a secret key in config/secrets.php.', 'MISSING_KEY');
    if (!preg_match('/^sk_(test|live)_/', $secret)) throw new PaystackException('Paystack secret key format is invalid.', 'INVALID_KEY_FORMAT');
    if ($cfg['mode'] === 'test' && !str_starts_with($secret, 'sk_test_')) throw new PaystackException('Paystack is configured for Test mode but the configured key is not a test key.', 'MODE_KEY_MISMATCH');
    if ($cfg['mode'] === 'live' && !str_starts_with($secret, 'sk_live_')) throw new PaystackException('Paystack is configured for Live mode but the configured key is not a live key.', 'MODE_KEY_MISMATCH');

    $url = rtrim(PAYSTACK_BASE_URL,'/').'/'.ltrim($endpoint,'/');
    $ch = curl_init($url);
    if ($ch === false) throw new PaystackException('PHP cURL is not available on this server.', 'CURL_UNAVAILABLE');
    $headers = ['Authorization: Bearer '.$secret,'Content-Type: application/json','Accept: application/json'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
    if ($payload !== null) {
        $json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if ($json===false) { curl_close($ch); throw new PaystackException('Unable to encode Paystack request.','JSON_ENCODE'); }
        curl_setopt($ch,CURLOPT_POSTFIELDS,$json);
    }
    $body=curl_exec($ch); $curlErr=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false) throw new PaystackException('Paystack connection failed: '.($curlErr ?: 'unknown cURL error'),'CURL_ERROR',$http);
    $data=json_decode($body,true);
    if (!is_array($data)) throw new PaystackException('Paystack returned an invalid response. HTTP '.$http,'INVALID_RESPONSE',$http);
    if ($http<200 || $http>=300 || empty($data['status'])) {
        $msg=trim((string)($data['message'] ?? 'Paystack request failed.'));
        throw new PaystackException($msg,'API_ERROR',$http);
    }
    return $data;
}

function verifyPaystackSignature(string $rawBody,string $signature): bool
{ $secret=paystackSecretKey(); return $secret!=='' && trim($signature)!=='' && hash_equals(hash_hmac('sha512',$rawBody,$secret),trim($signature)); }

function paystackDiagnostics(): array
{
    $cfg=paystackConfig(); $key=$cfg['key'];
    $result=['mode'=>$cfg['mode'],'key_configured'=>$key!=='','key_type'=>$key===''?'none':(str_starts_with($key,'sk_test_')?'test':(str_starts_with($key,'sk_live_')?'live':'invalid')),'mode_key_match'=>($key!=='') && (($cfg['mode']==='test'&&str_starts_with($key,'sk_test_'))||($cfg['mode']==='live'&&str_starts_with($key,'sk_live_'))),'api'=>'unknown','message'=>null];
    if (!$result['key_configured']) { $result['api']='failed'; $result['message']='Secret key is not configured.'; return $result; }
    if (!$result['mode_key_match']) { $result['api']='failed'; $result['message']='Paystack mode and key type do not match.'; return $result; }
    try { $r=paystackRequest('GET','/balance'); $result['api']='ok'; $result['message']=$r['message']??'Paystack API authentication succeeded.'; } catch(Throwable $e) { $result['api']='failed'; $result['message']=$e->getMessage(); }
    return $result;
}
