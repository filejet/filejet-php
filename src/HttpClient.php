<?php

declare(strict_types=1);

namespace FileJet;

use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Psr\Http\Message\ResponseInterface;

final class HttpClient
{
    public const METHOD_POST = 'POST';

    /** @var \Http\Client\HttpClient */
    private $client;
    /** @var \Http\Message\MessageFactory */
    private $messageFactory;

    public function __construct()
    {
        $this->client = HttpClientDiscovery::find();
        $this->messageFactory = MessageFactoryDiscovery::find();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param string|null $body
     * @return ResponseInterface
     * @throws RemoteFileJetException
     * @throws \Http\Client\Exception
     */
    public function sendRequest(
        string $method,
        string $uri,
        array $headers = [],
        ?string $body = null
    ): ResponseInterface {
        $response = $this->client->sendRequest(
            $this->messageFactory->createRequest(
                $method,
                $uri,
                $headers,
                $body
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new RemoteFileJetException($response);
        }

        return $response;
    }
}
