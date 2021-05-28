<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\Transaction;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Archivator
{
    private FirestoreClient $firestoreClient;
    /** @var array<mixed>|null */
    private ?array $mandatory;
    private GoogleFileStorage $googleFileManager;
    private LoggerInterface $logger;

    /**
     * @param array<mixed>|null $requiredFields
     */
    public function __construct(
        LoggerInterface $logger,
        FirestoreClient $firestoreClient,
        GoogleFileStorage $googleFileManager,
        ?array $requiredFields = null
    ) {
        $this->firestoreClient   = $firestoreClient;
        $this->mandatory         = $requiredFields;
        $this->googleFileManager = $googleFileManager;
        $this->logger            = $logger;
    }

    public function downloadAsStream(string $anwendungName, string $anwendungId): StreamInterface
    {
        $document = $this->firestoreClient->collection($anwendungName)->document($anwendungId);

        $snapshot = $document->snapshot();
        if (isset($snapshot['googleFileName']) && Uuid::isValid($snapshot['googleFileName'])) {
            $fileName = Uuid::fromString($snapshot['googleFileName']);

            return $this->googleFileManager->getByName($fileName);
        }

        $message = \sprintf(
            'Search params $anwendungsName "%s" or $anwendungsId "%s" invalid or File does not exist in bucket.',
            $anwendungName,
            $anwendungId
        );

        $this->logger->error($message);

        throw new \InvalidArgumentException($message);
    }

    /**
     * todo: add mime type check for a pdf
     *
     * @return mixed
     */
    public function putFile(
        string $filePathOrContent,
        string $anwendungName,
        string $anwendungId,
        string ...$field
    ) {
        try {
            if (\file_exists($filePathOrContent)) {
                $uuid = $this->googleFileManager->fromFile($filePathOrContent);
            } else {
                $uuid = $this->googleFileManager->fromBase64($filePathOrContent);
            }
        } catch (\Throwable $notOk) {
            $this->logger->error('File could not upload to Google cloud storage ' . $notOk->getMessage());

            return false;
        }

        $success = $this->firestoreClient->runTransaction(
            fn (Transaction $t) => $this->putMetaData($t, $anwendungName, $anwendungId, $uuid, ...$field)
        );

        if (! (bool) $success) {
            $this->googleFileManager->delete($uuid);
            $this->logger->error('Unsuccessfully persisting file meta data to Firestore DB.');

            return false;
        }

        return true;
    }

    /**
     * @internal please use this method internal only !!!
     */
    public function putMetaData(
        Transaction $transaction,
        string $anwendungsName,
        string $anwendungsId,
        UuidInterface $uuid,
        string ...$field
    ): bool {
        $collection = $this->firestoreClient->collection($anwendungsName);
        $query      = $collection->where('anwendungsId', '=', $anwendungsId);

        $documents = $query->documents();

        if (\count($documents->rows()) > 0) {
            $this->logger->error(
                \sprintf(
                    'Another document with id "%s" already exist in collection "%s".',
                    $anwendungsId,
                    $anwendungsName
                )
            );

            return false;
        }

        $document     = $collection->document($anwendungsId);
        $documentData = [
            'anwendungsId'   => $anwendungsId,
            'googleFileName' => $uuid->toString(),
            'createdAtTs'    => \time(),
            'createdAt'      => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
        ];
        $required     = $this->mandatory;

        foreach ($field as $value) {
            $fieldDefinition = \explode(':', $value);

            if (\count($fieldDefinition) < 2) {
                $this->logger->error('Field expected in form fieldName:fieldValue given ' . $value . '.');

                return false;
            }

            if (\is_array($required)) {
                unset($required[$fieldDefinition[0]]);
            }

            $documentData[$fieldDefinition[0]] = $fieldDefinition[1];
        }

        if ($required !== null && \count($required) > 0) {
            $this->logger->error('One of required fields was not present');

            return false;
        }

        $transaction->set($document, $documentData);

        return true;
    }
}
