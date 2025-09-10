<?php

class CryptoHelper
{
    private string $apiKey;
    private string $selfSalt;
    private bool $debug = false;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->apiKey = SYNC_API_KEY;
        $this->selfSalt = SYNC_PEERS[SYNC_SELF]['salt'];
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
        $this->logger = $logger;
    }

    public function generateAuthSignature(string $peer): string
    {
        $peerSalt = SYNC_PEERS[$peer]['salt'] ?? '';
        $salts = [$this->selfSalt, $peerSalt];
        sort($salts); // обеспечивает симметрию

        $sigInput = $this->apiKey . $salts[0] . $salts[1];

        // Симметричное сообщение по отсортированным именам пиров
        $names = [SYNC_SELF, $peer];
        sort($names);
        $message = implode(':', $names);

        $signature = hash_hmac('sha256', $message, $sigInput);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[SIGNATURE] Generating for peer: $peer");
            $this->logger->debug("[SIGNATURE] API Key: {$this->apiKey}");
            $this->logger->debug("[SIGNATURE] Salts (sorted): " . json_encode($salts));
            $this->logger->debug("[SIGNATURE] sigInput: $sigInput");
            $this->logger->debug("[SIGNATURE] Peers (sorted): " . json_encode($names));
            $this->logger->debug("[SIGNATURE] Message: $message");
            $this->logger->debug("[SIGNATURE] Signature: $signature");
        }

        return $signature;
    }



    public function verifyAuthSignature(string $peer, string $signature): bool
    {
        #        $this->logger->log("breakpoint unauthorized");die('1');
        $expected = $this->generateAuthSignature($peer);
        $result = hash_equals($expected, $signature);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[VERIFY] Peer: $peer");
            $this->logger->debug("[VERIFY] Provided Signature: $signature");
            $this->logger->debug("[VERIFY] Expected Signature: $expected");
            $this->logger->debug("[VERIFY] Match: " . ($result ? 'yes' : 'no'));
        }

        return $result;
    }

    public function encrypt(string $json, string $peerName): string
    {
        $key = $this->buildKey($peerName);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $result = base64_encode($iv . $encrypted);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[ENCRYPT] Plain: $json");
            $this->logger->debug("[ENCRYPT] Key: " . bin2hex($key));
            $this->logger->debug("[ENCRYPT] IV: " . bin2hex($iv));
            $this->logger->debug("[ENCRYPT] Result: $result");
        }

        return $result;
    }

    public function decrypt(string $b64data, string $peerName): ?string
    {
        $decoded = base64_decode($b64data, true);
        if (!is_string($decoded) || strlen($decoded) < 17) return null;

        $iv = substr($decoded, 0, 16);
        $ct = substr($decoded, 16);
        $key = $this->buildKey($peerName);
        $plain = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[DECRYPT] Input: $b64data");
            $this->logger->debug("[DECRYPT] Key: " . bin2hex($key));
            $this->logger->debug("[DECRYPT] IV: " . bin2hex($iv));
            $this->logger->debug("[DECRYPT] Decrypted: $plain");
        }

        return is_string($plain) ? $plain : null;
    }

    public function encryptRawLine(string $payload, string $key): string
    {
        $iv = random_bytes(16);
        $enc = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $result = base64_encode($iv . $enc);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[ENCRYPT_RAW] Payload: $payload");
            $this->logger->debug("[ENCRYPT_RAW] Key: " . bin2hex($key));
            $this->logger->debug("[ENCRYPT_RAW] IV: " . bin2hex($iv));
            $this->logger->debug("[ENCRYPT_RAW] Result: $result");
        }

        return $result;
    }

    public function decryptRawLine(string $line, string $key): ?string
    {
        $decoded = base64_decode($line, true);
        if (!is_string($decoded) || strlen($decoded) < 17) return null;

        $iv = substr($decoded, 0, 16);
        $ct = substr($decoded, 16);
        $pt = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[DECRYPT_RAW] Input: $line");
            $this->logger->debug("[DECRYPT_RAW] Key: " . bin2hex($key));
            $this->logger->debug("[DECRYPT_RAW] IV: " . bin2hex($iv));
            $this->logger->debug("[DECRYPT_RAW] Decrypted: $pt");
        }

        return is_string($pt) ? $pt : null;
    }

    public function buildKey(string $peerName): string
    {
        if (!isset(SYNC_PEERS[$peerName]['salt'])) {
            throw new \RuntimeException("Unknown peer: $peerName");
        }

        $salts = [$this->selfSalt, SYNC_PEERS[$peerName]['salt']];
        sort($salts); // обеспечивает симметрию

        $seed = $this->apiKey . $salts[0] . $salts[1];
        $key = hash('sha256', $seed, true);

        if ($this->debug && $this->logger) {
            $this->logger->debug("[BUILD_KEY] Peer: $peerName");
            $this->logger->debug("[BUILD_KEY] Self Salt: {$this->selfSalt}");
            $this->logger->debug("[BUILD_KEY] Peer Salt: " . SYNC_PEERS[$peerName]['salt']);
            $this->logger->debug("[BUILD_KEY] Salts (sorted): " . json_encode($salts));
            $this->logger->debug("[BUILD_KEY] API Key: {$this->apiKey}");
            $this->logger->debug("[BUILD_KEY] Seed: $seed");
            $this->logger->debug("[BUILD_KEY] Resulting Key: " . bin2hex($key));
        }

        return $key;
    }
}
