<?php

class AmazonCreatorsApiClient {
    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    /** @var string */
    private $version;

    /** @var string */
    private $marketplace;

    /**
     * @param string $clientId     Your Credential ID from Associates Central
     * @param string $clientSecret Your Credential Secret
     * @param string $version      Your Credential Version (e.g., '3.2' or '2.1')
     * @param string $marketplace  The target marketplace (e.g., 'www.amazon.com')
     */
    public function __construct($clientId, $clientSecret, $version, $marketplace) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->version = trim($version);
        $this->marketplace = strtolower(trim($marketplace));
    }

    /**
     * Operation: GetBrowseNodes
     * @param array $payload
     * @return array
     */
    public function GetBrowseNodes(array $payload) {
        return $this->executeRequest('getBrowseNodes', $payload);
    }

    /**
     * Operation: GetItems
     * @param array $payload
     * @return array
     */
    public function GetItems(array $payload) {
        return $this->executeRequest('getItems', $payload);
    }

    /**
     * Operation: SearchItems
     * @param array $payload
     * @return array
     */
    public function SearchItems(array $payload) {
        return $this->executeRequest('searchItems', $payload);
    }

    /**
     * Operation: GetVariations
     * @param array $payload
     * @return array
     */
    public function GetVariations(array $payload) {
        return $this->executeRequest('getVariations', $payload);
    }

    /**
     * Core request executor via native cURL
     */
    private function executeRequest($operation, array $payload) {
        $token = $this->fetchAccessToken();
        $url = "https://creatorsapi.amazon/catalog/v1/" . $operation;

        $headers = [
            'Authorization: Bearer ' . $token,
            'x-marketplace: ' . $this->marketplace,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $payload['marketplace'] = $this->marketplace;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Request Error: " . $errorMsg);
        }

        curl_close($ch);
        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorDetail = isset($decoded['message']) ? $decoded['message'] : $response;
            throw new Exception("Creators API Error [HTTP {$httpCode}]: " . $errorDetail);
        }

        return $decoded;
    }

    /**
     * Handles retrieving and caching access tokens locally
     */
    private function fetchAccessToken() {
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'amz_token_' . md5($this->clientId . $this->marketplace) . '.json';

        // Check if cached token is still valid (with a 60-second buffer safety margin)
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if ($cache && isset($cache['access_token']) && isset($cache['expires_at']) && $cache['expires_at'] > time() + 60) {
                return $cache['access_token'];
            }
        }

        // Cache miss: Build authorization context
        $region = $this->getOAuthRegion();
        $tokenUrl = $this->getTokenEndpoint($region);
        $scope = (strpos($this->version, '2.') === 0) ? 'creatorsapi/default' : 'creatorsapi::default';

        $postData = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $scope
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Authentication token generation failed [HTTP {$httpCode}]: " . $response);
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Token missing from server response: " . $response);
        }

        // Cache the token structure safely
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        file_put_contents($cacheFile, json_encode([
            'access_token' => $data['access_token'],
            'expires_at'   => time() + $expiresIn
        ]));

        return $data['access_token'];
    }

    private function getOAuthRegion() {
        $naMarketplaces = ['www.amazon.com', 'www.amazon.ca', 'www.amazon.com.mx', 'www.amazon.com.br'];
        $feMarketplaces = ['www.amazon.co.jp', 'www.amazon.com.au', 'www.amazon.sg'];

        if (in_array($this->marketplace, $naMarketplaces)) {
            return 'NA';
        }
        if (in_array($this->marketplace, $feMarketplaces)) {
            return 'FE';
        }
        return 'EU'; // Default fallback for UK, DE, FR, IT, ES, etc.
    }

    private function getTokenEndpoint($region) {
        $isV2 = (strpos($this->version, '2.') === 0);

        if ($isV2) {
            switch ($region) {
                case 'NA': return 'https://creatorsapi.auth.us-east-1.amazoncognito.com/oauth2/token';
                case 'FE': return 'https://creatorsapi.auth.us-west-2.amazoncognito.com/oauth2/token';
                case 'EU':
                default:   return 'https://creatorsapi.auth.eu-south-2.amazoncognito.com/oauth2/token';
            }
        } else {
            switch ($region) {
                case 'NA': return 'https://api.amazon.com/auth/o2/token';
                case 'FE': return 'https://api.amazon.co.jp/auth/o2/token';
                case 'EU':
                default:   return 'https://api.amazon.co.uk/auth/o2/token';
            }
        }
    }
}
