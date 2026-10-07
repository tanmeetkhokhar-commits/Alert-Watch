<?php


final class AlertWatchRssFetcher
{
    private int $timeout;
    private string $userAgent;

    public function __construct(int $timeout = 20, string $userAgent = 'AlertWatch/1.0 RSS Fetcher')
    {
        $this->timeout = max(5, $timeout);
        $this->userAgent = $userAgent;
    }

   
    public function fetch(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid RSS URL.');
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('RSS URL must use HTTP or HTTPS.');
        }

        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url);
        }

        return $this->fetchWithStreams($url);
    }

    private function fetchWithCurl(string $url): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
                'Accept-Encoding: gzip, deflate',
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);

        if ($body === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('RSS request failed: ' . $message);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('RSS server returned HTTP ' . $httpCode . '.');
        }

        if (trim($body) === '') {
            throw new RuntimeException('RSS source returned an empty response.');
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
            'http_code' => $httpCode,
        ];
    }

    
    private function fetchWithStreams(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
                'header' => "User-Agent: {$this->userAgent}\r\nAccept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuntimeException('RSS request failed.');
        }

        $httpCode = 200;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                    $httpCode = (int) $match[1];
                }
            }
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('RSS server returned HTTP ' . $httpCode . '.');
        }

        return [
            'body' => $body,
            'content_type' => '',
            'http_code' => $httpCode,
        ];
    }
}
