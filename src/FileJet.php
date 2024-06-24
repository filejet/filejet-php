<?php

declare(strict_types=1);

namespace FileJet;

use FileJet\Messages\DownloadInstruction;
use FileJet\Messages\UploadInstruction;
use FileJet\Messages\UploadInstructionFactory;
use FileJet\Messages\UploadRequest;

final class FileJet
{
    /** @var HttpClient */
    private $httpClient;
    /** @var Config */
    private $config;
    /** @var Mutation */
    private $mutation;

    public function __construct(HttpClient $httpClient, Config $config, Mutation $mutation)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->mutation = $mutation;
    }

    public function getUrl(FileInterface $file): string
    {
        $url = "{$this->config->getPublicUrl()}/{$this->normalizeId($file->getIdentifier())}";

        if ($this->config->isAutoMode() && $this->mutation->autoIsEnabled($file->getMutation())) {
            $file = new File($file->getIdentifier(), $this->mutation->toAutoMutation($file->getMutation()));
        }

        if ($this->config->isAutoMode() && false === $this->mutation->autoIsEnabled($file->getMutation())) {
            $file = new File($file->getIdentifier(), $this->mutation->removeAutoMutation($file->getMutation()));
        }

        if ($file->getMutation() !== null) {
            $url = "{$url}/{$file->getMutation()}";
        }

        return $url;
    }

    public function getPrivateUrl(string $fileId, int $expires, string $mutation = ''): DownloadInstruction
    {
        return new DownloadInstruction(
            $this->request('file.privateUrl', $this->getRequestParameters($fileId, $expires, $mutation))
        );
    }

    /**
     * @param string[] $fileIdentifiers
     * @return DownloadInstruction[]
     */
    public function bulkPrivateUrl(array $fileIdentifiers, int $expires, string $mutation = ''): array
    {
        if (!$fileIdentifiers) {
            return [];
        }

        $orderedIdentifiers = array_values($fileIdentifiers);
        $body = [];
        foreach ($fileIdentifiers as $identifier) {
            $body[] = $this->getRequestParameters($identifier, $expires, $mutation);
        }

        $decodedBulkResponse = json_decode($this->request('file.privateUrl', $body)->getBody()->getContents(), true);
        $downloadInstructions = [];
        /** @var string[][] $instructionData */
        foreach ($decodedBulkResponse as $key => $instructionData) {
            $downloadInstructions[$orderedIdentifiers[$key]] = new DownloadInstruction($instructionData['url']);
        }

        return $downloadInstructions;
    }

    public function getDetentionUrl(string $fileId, int $expires, string $mutation = ''): DownloadInstruction
    {
        return new DownloadInstruction(
            $this->request('file.detentionUrl', $this->getRequestParameters($fileId, $expires, $mutation))
        );
    }

    /**
     * @param string[] $fileIdentifiers
     * @return DownloadInstruction[]
     */
    public function bulkDetentionUrl(array $fileIdentifiers, int $expires, string $mutation = ''): array
    {
        if (!$fileIdentifiers) {
            return [];
        }

        $orderedIdentifiers = array_values($fileIdentifiers);
        $body = [];
        foreach ($fileIdentifiers as $identifier) {
            $body[] = $this->getRequestParameters($identifier, $expires, $mutation);
        }

        $decodedBulkResponse = \json_decode($this->request('file.detentionUrl', $body)->getBody()->getContents(), true);
        $downloadInstructions = [];

        /** @var string[][] $instructionData */
        foreach ($decodedBulkResponse as $key => $instructionData) {
            $downloadInstructions[$orderedIdentifiers[$key]] = new DownloadInstruction($instructionData['url']);
        }

        return $downloadInstructions;
    }

    /**
     * @return mixed[]
     */
    private function getRequestParameters(string $fileId, int $expires, string $mutation = ''): array
    {
        $requestParameters = ['fileId' => $this->normalizeId($fileId), 'expires' => $expires];

        $customDomain = $this->config->getCustomDomain();
        if ($customDomain) {
            $requestParameters['customDomain'] = $customDomain;
        }

        $mutation = $this->resolveAutoMutation($mutation);
        if ($mutation) {
            $requestParameters['mutation'] = $mutation;
        }

        return $requestParameters;
    }

    public function getExternalUrl(string $url, string $mutation = '')
    {
        $mutation = $this->resolveAutoMutation($mutation);

        if ($mutation === null) $mutation = '';

        return "{$this->config->getPublicUrl()}/ext/{$mutation}?src={$this->sign($url)}";
    }

    public function uploadFile(UploadRequest $request): UploadInstruction
    {
        return UploadInstructionFactory::createFromResponse(
            $this->request('file.requestUpload', [
                'contentType' => $request->getContentType(),
                'expires' => $request->getExpires(),
                'access' => $request->getAccess(),
                'filename' => $request->getFilename(),
            ])
        );
    }

    /**
     * @param UploadRequest[] $requests
     *
     * @return UploadInstruction[]
     */
    public function bulkUploadFiles(array $requests): array
    {
        $body = [];
        foreach ($requests as $request) {
            $body[] = [
                'contentType' => $request->getContentType(),
                'expires' => $request->getExpires(),
                'access' => $request->getAccess(),
                'filename' => $request->getFilename(),
            ];
        }

        $decodedBulkResponse = json_decode($this->request('file.requestUpload', $body)->getBody()->getContents(), true);
        $uploadInstructions = [];
        /** @var string[][] $instructionData */
        foreach ($decodedBulkResponse as $instructionData) {
            $uploadInstructions[] = UploadInstructionFactory::createFromArray($instructionData);
        }

        return $uploadInstructions;
    }

    public function deleteFile(string $fileId): void
    {
        $this->request('file.delete', ['fileId' => $this->normalizeId($fileId)]);
    }

    private function request(string $operation, array $body)
    {
        return $this->httpClient->sendRequest(
            HttpClient::METHOD_POST,
            "{$this->config->getStorageManagerUrl()}/$operation",
            [
                'Authorization' => $this->config->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            json_encode($body)
        );
    }

    private function normalizeId(string $fileId): string
    {
        return preg_replace('/[^a-z0-9]/', 'x', strtolower($fileId));
    }

    private function sign(string $url): string
    {
        if ($this->config->getSignatureSecret() === null) return $url;

        return urlencode($url).'&sig='.hash_hmac('sha256', $url, $this->config->getSignatureSecret());
    }

    /**
     * @deprecated Please do use the FileJet/Mutation
     */
    public function toMutation(FileInterface $file, string $mutation = null) : ?string {
        return $this->mutation->toMutation($file, $mutation);
    }

    private function resolveAutoMutation(string $mutation = ''): ?string
    {
        if ($this->config->isAutoMode() && $this->mutation->autoIsEnabled($mutation)) {
            $mutation = $this->mutation->toAutoMutation($mutation);
        }

        if ($this->config->isAutoMode() && false === $this->mutation->autoIsEnabled($mutation)) {
            $mutation = $this->mutation->removeAutoMutation($mutation);
        }

        return $mutation;
    }
}
