<?php

declare(strict_types=1);

namespace FileJet;

class Mutation
{
    public function autoIsEnabled(?string $mutation = ''): bool
    {
        return strpos($mutation ?? '', 'auto=false') === false;
    }

    public function toAutoMutation(?string $mutation = ''): string
    {
        return $mutation ? "{$mutation},auto" : 'auto';
    }

    public function removeAutoMutation(?string $mutation = ''): ?string
    {
        $output = preg_replace('/,?auto=false|auto=false,?/m', '', $mutation);

        return $output === '' ? null : $output;
    }

    public function toMutation(FileInterface $file, ?string $mutation = null) : ?string
    {
        $output = $file->getMutation() ?? '';
        $separator = empty($output) || empty($mutation) ? '' : ',';

        return "{$output}{$separator}{$mutation}";
    }
}
