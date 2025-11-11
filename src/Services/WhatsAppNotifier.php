<?php

class WhatsAppNotifier
{
    private $token;
    private $instance;
    private $enabled;
    private $defaultRecipient;
    private $httpAvailable = false;

    public function __construct()
    {
        $this->token = $_ENV['WHATSAPP_TOKEN'] ?? '';
        $this->instance = $_ENV['WHATSAPP_INSTANCE'] ?? '';
        $this->enabled = filter_var($_ENV['WHATSAPP_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->defaultRecipient = $_ENV['WHATSAPP_ADMIN_PHONE'] ?? '';

        if (class_exists('HTTP_Request2')) {
            $this->httpAvailable = true;
        } else {
            $path = stream_resolve_include_path('HTTP/Request2.php');
            if ($path) {
                require_once $path;
                $this->httpAvailable = class_exists('HTTP_Request2');
            } else {
                error_log('WhatsAppNotifier: HTTP_Request2.php no encontrado. Se usará cURL.');
            }
        }
    }

    public function sendLoginAlert(string $message, ?string $recipient = null): bool
    {
        if (!$this->enabled || empty($this->token) || empty($this->instance)) {
            return false;
        }

        $to = $recipient ? $this->normalizePhone($recipient) : $this->normalizePhone($this->defaultRecipient);
        if (!$to) {
            return false;
        }

        return $this->sendMessage($to, $message);
    }

    private function normalizePhone(string $phone): ?string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }
        if (strpos($trimmed, '+') === 0) {
            return urlencode($trimmed);
        }
        if (strpos($trimmed, '%2B') === 0) {
            return $trimmed;
        }
        return urlencode('+' . $trimmed);
    }

    private function sendMessage(string $to, string $body): bool
    {
        $params = [
            'token' => $this->token,
            'to'    => $to,
            'body'  => $body,
            'priority' => 10
        ];

        if ($this->httpAvailable) {
            try {
                $request = new HTTP_Request2();
                $request->setUrl("https://api.ultramsg.com/{$this->instance}/messages/chat");
                $request->setMethod(HTTP_Request2::METHOD_POST);
                $request->setHeader(['Content-Type' => 'application/x-www-form-urlencoded']);
                $request->setConfig(['follow_redirects' => true]);
                $request->addPostParameter($params);
                $response = $request->send();
                if ($response->getStatus() === 200) {
                    return true;
                }
                error_log('WhatsAppNotifier HTTP_Request2 respuesta: ' . $response->getStatus() . ' ' . $response->getBody());
            } catch (Throwable $e) {
                error_log('WhatsAppNotifier error HTTP_Request2: ' . $e->getMessage());
            }
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.ultramsg.com/{$this->instance}/messages/chat",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_TIMEOUT => 30
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                error_log('WhatsAppNotifier cURL error: ' . $error);
                return false;
            }

            if ($status === 200) {
                return true;
            }
            error_log('WhatsAppNotifier cURL respuesta: ' . $status . ' ' . $response);
        } else {
            error_log('WhatsAppNotifier: cURL no disponible.');
        }

        return false;
    }
}
