<?php

declare(strict_types=1);

namespace FileJet\Messages;

use Psr\Http\Message\ResponseInterface;

class UploadInstructionFactory
{
    /**
     * @param string[][] $data
     */
    public static function createFromArray(array $data): UploadInstruction
    {
        $identifier = $data['fileId'];

        $uploadFormatData = $data['uploadFormat'];
        $uploadFormat = new UploadFormat(
            $uploadFormatData['url'],
            $uploadFormatData['httpMethod'],
            $uploadFormatData['headers']
        );

        return new UploadInstruction($identifier, $uploadFormat);
    }

    public static function createFromResponse(string $response): UploadInstruction
    {
        $data = json_decode($response, true);

        return static::createFromArray($data[0]);
    }
}
