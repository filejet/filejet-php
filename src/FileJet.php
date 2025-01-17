<?php

declare(strict_types=1);

namespace FileJet;

use Aws\Lambda\LambdaClient;
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
    /** @var LambdaClient */
    private $lambdaClient;

    public function __construct(HttpClient $httpClient, Config $config, Mutation $mutation, LambdaClient $lambdaClient)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->mutation = $mutation;
        $this->lambdaClient = $lambdaClient;
    }

    public function getUrl(FileInterface $file): string
    {
        $url = "{$this->config->getPublicUrl()}/{$this->ensureValidId($file->getIdentifier())}";

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
        return $this->getUrlByType('privateUrl', $fileId, $expires, $mutation);
    }

    /**
     * @param string[] $fileIdentifiers
     * @return DownloadInstruction[]
     */
    public function bulkPrivateUrl(array $fileIdentifiers, int $expires, string $mutation = ''): array
    {
        return $this->bulkUrlByType('privateUrl', $fileIdentifiers, $expires, $mutation);
    }

    public function getDetentionUrl(string $fileId, int $expires, string $mutation = ''): DownloadInstruction
    {
        return $this->getUrlByType('detentionUrl', $fileId, $expires, $mutation);
    }

    /**
     * @param string[] $fileIdentifiers
     * @return DownloadInstruction[]
     */
    public function bulkDetentionUrl(array $fileIdentifiers, int $expires, string $mutation = ''): array
    {
        return $this->bulkUrlByType('detentionUrl', $fileIdentifiers, $expires, $mutation);
    }

    private function getUrlByType(string $urlType, string $fileId, int $expires, string $mutation = ''): DownloadInstruction
    {
        return new DownloadInstruction(
            $this->request("file.$urlType", $this->getRequestParameters($fileId, $expires, $mutation))
        );
    }

    /**
     * @param string[] $fileIdentifiers
     * @return DownloadInstruction[]
     */
    private function bulkUrlByType(string $urlType, array $fileIdentifiers, int $expires, string $mutation = ''): array
    {
        if (!$fileIdentifiers) {
            return [];
        }

        $orderedIdentifiers = array_values($fileIdentifiers);
        $body = [];
        foreach ($fileIdentifiers as $identifier) {
            $params = $this->getRequestParameters($identifier, $expires, $mutation);
            $params['$command'] = "file.$urlType";
            $body[] = $params;
        }

        $decodedBulkResponse = json_decode(
            (string)$this->lambdaClient->invoke([
                'FunctionName' => $this->config->getLambdaControllerFunctionName(),
                'Payload' => json_encode($body),
            ])->get('Payload'),
            true
        );

        $downloadInstructions = [];
        /** @var string[] $instructionData */
        foreach ($decodedBulkResponse as $key => $instructionData) {
            if (isset($instructionData['url'])) {
                $downloadInstructions[$orderedIdentifiers[$key]] = new DownloadInstruction($instructionData['url']);

                continue;
            }

            // try single file call
            $downloadInstruction = $this->getUrlByType($urlType, $orderedIdentifiers[$key], $expires, $mutation);
            if ($downloadInstruction->getUrl()) {
                $downloadInstructions[$orderedIdentifiers[$key]] = $downloadInstruction->getUrl();

                continue;
            }

            // set empty string as a fallback to prevent errors down the line
            $downloadInstructions[$orderedIdentifiers[$key]] = new DownloadInstruction('');
        }

        return $downloadInstructions;
    }

    /**
     * @return mixed[]
     */
    private function getRequestParameters(string $fileId, int $expires, string $mutation = ''): array
    {
        $requestParameters = ['fileId' => $this->ensureValidId($fileId), 'ttl' => $expires];

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
            $this->request('file.upload', [
                'contentType' => $request->getContentType(),
                'ttl' => $request->getExpires(),
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
                '$command' => 'file.upload',
                'contentType' => $request->getContentType(),
                'ttl' => $request->getExpires(),
                'access' => $request->getAccess(),
                'filename' => $request->getFilename(),
            ];
        }

        $decodedBulkResponse = json_decode(
            (string)$this->lambdaClient->invoke([
                'FunctionName' => $this->config->getLambdaControllerFunctionName(),
                'Payload' => json_encode($body),
            ])->get('Payload'),
            true
        );
        $uploadInstructions = [];
        /** @var string[][] $instructionData */
        foreach ($decodedBulkResponse as $instructionData) {
            $uploadInstructions[] = UploadInstructionFactory::createFromArray($instructionData);
        }

        return $uploadInstructions;
    }

    public function deleteFile(string $fileId): void
    {
        $this->request('file.delete', ['fileId' => $this->ensureValidId($fileId)]);
    }

    private function request(string $operation, array $body)
    {
        return (string)$this->lambdaClient->invoke([
            'FunctionName' => $this->config->getLambdaControllerFunctionName(),
            'Payload' => json_encode([
                array_merge($body, ['$command' => $operation])
            ]),
        ])->get('Payload');
    }

    private function ensureValidId(string $fileId): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', 'x', $fileId);
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
