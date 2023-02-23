<?php

declare(strict_types=1);

namespace FileJet;

class Config
{
    /** @var string */
    private $lambdaControllerFunctionName;
    /** @var string */
    private $baseUrl;
    /** @var string */
    private $signatureSecret;
    /** @var bool */
    private $autoMode;
    /** @var string */
    private $customDomain;

    public function __construct(
        string $lambdaControllerFunctionName,
        string $customDomain,
        string $signatureSecret = null,
        bool $autoMode = true,
        string $baseUrl = null
    ) {
        $this->lambdaFunctionName = $lambdaControllerFunctionName;
        $this->signatureSecret = $signatureSecret;
        $this->autoMode = $autoMode;
        $this->baseUrl = $baseUrl;
        $this->customDomain = $customDomain;
    }

    public function getLambdaControllerFunctionName(): string
    {
        return $this->lambdaFunctionName;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function getSignatureSecret(): ?string
    {
        return $this->signatureSecret;
    }

    public function isAutoMode(): bool
    {
        return $this->autoMode;
    }

    public function getPublicUrl(): string
    {
        return "https://{$this->getCustomDomain()}";
    }

    public function getCustomDomain(): ?string
    {
        return $this->customDomain;
    }
}
