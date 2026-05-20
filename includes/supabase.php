<?php
// includes/supabase.php
// Simple Supabase helper using cURL (no Composer required)

class SupabaseClient {
    private $projectUrl;
    private $apiKey;

    public function __construct($projectUrl, $apiKey) {
        $this->projectUrl = rtrim($projectUrl, '/');
        $this->apiKey = $apiKey;
    }

    // Generic request method
    public function request($method, $endpoint, $data = null, $headers = []) {
        $url = $this->projectUrl . $endpoint;
        $ch = curl_init($url);

        $defaultHeaders = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'response' => json_decode($response, true),
            'error' => $error
        ];
    }

    // Example: fetch from a table
    public function select($table, $params = []) {
        $query = http_build_query($params);
        $endpoint = "/rest/v1/$table" . ($query ? "?$query" : "");
        return $this->request('GET', $endpoint);
    }

    // Example: insert into a table
    public function insert($table, $data) {
        $endpoint = "/rest/v1/$table";
        return $this->request('POST', $endpoint, $data);
    }

    // Example: update a table
    public function update($table, $data, $params = []) {
        $query = http_build_query($params);
        $endpoint = "/rest/v1/$table" . ($query ? "?$query" : "");
        return $this->request('PATCH', $endpoint, $data);
    }

    // Example: delete from a table
    public function delete($table, $params = []) {
        $query = http_build_query($params);
        $endpoint = "/rest/v1/$table" . ($query ? "?$query" : "");
        return $this->request('DELETE', $endpoint);
    }
}

// Usage example:
// $supabase = new SupabaseClient('https://YOUR_PROJECT_ID.supabase.co', 'YOUR_ANON_KEY');
// $result = $supabase->select('your_table');
// var_dump($result);
