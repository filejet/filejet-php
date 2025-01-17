<?php

declare(strict_types=1);

namespace FileJet\Messages;

use Psr\Http\Message\ResponseInterface;

final class DownloadInstruction
{
    /** @var string */
    private $url;

    /**
     * @param ResponseInterface|string $response
     */
    public function __construct($response)
    {
        if (is_string($response)) {
            $this->url = $response;

            return;
        }

        $data = json_decode($response->getBody()->getContents(), true);
        if (isset($data['url'])) {
            $this->url = $data['url'];

            return;
        }

        $this->url = '';
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}