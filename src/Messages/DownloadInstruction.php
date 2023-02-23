<?php

declare(strict_types=1);

namespace FileJet\Messages;

use Psr\Http\Message\ResponseInterface;

final class DownloadInstruction
{
    /** @var string */
    private $url;

    public function __construct(string $response)
    {
        $data = json_decode($response, true);
        $this->url = $data[0]['url'];
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}
