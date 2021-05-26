<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

interface GoogleFileStorage
{
    public function fromFile(string $filename): \Ramsey\Uuid\UuidInterface;

    public function fromBase64(string $content): \Ramsey\Uuid\UuidInterface;

    public function delete(\Ramsey\Uuid\UuidInterface $uuid): void;
}
