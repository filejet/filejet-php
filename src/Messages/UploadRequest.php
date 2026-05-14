<?php

declare(strict_types=1);

namespace FileJet\Messages;

final class UploadRequest
{
    private const DEFAULT_TTL_IN_SECONDS = 60;

    public const PUBLIC_ACCESS = 'public';
    public const PRIVATE_ACCESS = 'private';

    /** @var string */
    private $contentType;
    /** @var string */
    private $access;
    /** @var int */
    private $expires;
    /** @var string|null */
    private $filename;

    public function __construct(string $contentType, string $access = self::PUBLIC_ACCESS, int $expires = self::DEFAULT_TTL_IN_SECONDS, ?string $filename = null)
    {
        $this->contentType = $contentType;
        $this->access = $access;
        $this->expires = $expires;
        $this->filename = $filename;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getAccess(): string
    {
        return $this->access;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
