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

            if (isset($data[0]['url'])) {
                $this->url = $data[0]['url'];

                return;
            }

            $this->url = $response;
        } catch (\Throwable $e) {
            //if $response contains invalid json
            $this->url = '';
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}
