<?php

declare(strict_types=1);

namespace FileJet\Messages;

final class DownloadInstruction
{
    /** @var string */
    private $url;

    public function __construct(string $response)
    {
        try {
            $data = json_decode($response, true);
            $this->url = $data[0]['url'];
        } catch (\Throwable $e) {
            $this->url = $response;
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}
