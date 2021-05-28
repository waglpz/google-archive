<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\UuidInterface;

interface GoogleFileStorage
{
    public function fromFile(string $filename): UuidInterface;

    public function fromBase64(string $content): UuidInterface;

    public function delete(UuidInterface $uuid): void;

    public function getByName(UuidInterface $googleFileName): StreamInterface;
}
