<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GoogleFileManager implements GoogleFileStorage
{
    private StorageClient $googleStorageClient;
    private string $bucketName;

    public function __construct(StorageClient $googleStorageClient, string $bucketName)
    {
        $this->googleStorageClient = $googleStorageClient;
        $this->bucketName          = $bucketName;
    }

    public function fromFile(string $filename): UuidInterface
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        if (\file_exists($filename) || \is_readable($filename)) {
            $uuid = Uuid::uuid4();
            $bucket->upload(\fopen($filename, 'rb'), ['name' => $uuid->toString()]);

            return $uuid;
        }

        throw new \RuntimeException('File ' . $filename . ' does not exist or not readable.');
    }

    public function fromBase64(string $content): UuidInterface
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        $binary = \base64_decode($content, false);
        if ($binary !== false) {
            $uuid = Uuid::uuid4();
            $bucket->upload($binary, ['name' => $uuid->toString()]);

            return $uuid;
        }

        throw new \RuntimeException('Base 64 content is not properly decoded.');
    }

    public function object(UuidInterface $name): StorageObject
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        return $bucket->object($name->toString());
    }

    public function delete(\Ramsey\Uuid\UuidInterface $uuid): void
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        $bucket->object($uuid->toString())->delete();
    }
}
