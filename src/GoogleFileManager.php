<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GoogleFileManager implements GoogleFileStorage
{
    private StorageClient $googleStorageClient;
    private string $bucketName;
    private LoggerInterface $logger;

    public function __construct(StorageClient $googleStorageClient, string $bucketName, LoggerInterface $logger)
    {
        $this->googleStorageClient = $googleStorageClient;
        $this->bucketName          = $bucketName;
        $this->logger              = $logger;
    }

    public function fromFile(string $filename): UuidInterface
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        if (\file_exists($filename) || \is_readable($filename)) {
            $uuid        = Uuid::uuid4();
            $fileHandler = \fopen($filename, 'rb');

            if (! \is_resource($fileHandler)) {
                $this->logger->error('File is invalid.');

                throw new \RuntimeException('File is invalid.');
            }

            $bucket->upload($fileHandler, ['name' => $uuid->toString()]);
            $this->logger->debug('File was uploaded');

            return $uuid;
        }

        $message = 'File ' . $filename . ' does not exist or not readable.';
        $this->logger->error($message);

        throw new \RuntimeException($message);
    }

    public function fromBase64(string $content): UuidInterface
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        $binary = \base64_decode($content, true);
        if ($binary !== false) {
            $uuid = Uuid::uuid4();
            $bucket->upload($binary, ['name' => $uuid->toString()]);
            $this->logger->debug('File was uploaded');

            return $uuid;
        }

        $message = 'Base 64 content is not properly decoded.';
        $this->logger->error($message);

        throw new \RuntimeException($message);
    }

    public function object(UuidInterface $name): StorageObject
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        return $bucket->object($name->toString());
    }

    public function delete(UuidInterface $uuid): void
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        $this->logger->debug('Try to delete a File');
        $bucket->object($uuid->toString())->delete();
    }

    public function getByName(UuidInterface $googleFileName): StreamInterface
    {
        $bucket = $this->googleStorageClient->bucket($this->bucketName);

        $fileObject = $bucket->object($googleFileName->toString());

        if ($fileObject->exists()) {
            return $fileObject->downloadAsStream();
        }

        $message =  \sprintf(
            'File name is invalid given "%s" or File does not exista in bucket "%s".',
            $googleFileName->toString(),
            $this->bucketName
        );

        $this->logger->error($message);

        throw new \InvalidArgumentException($message);
    }
}
