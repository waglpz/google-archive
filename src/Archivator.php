<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\Transaction;
use Ramsey\Uuid\UuidInterface;

final class Archivator
{
    private FirestoreClient $firestoreClient;
    /** @var array<mixed>|null */
    private ?array $mandatory;
    private GoogleFileStorage $googleFileManager;

    /**
     * @param array<mixed>|null $mandatory
     */
    public function __construct(
        FirestoreClient $firestoreClient,
        GoogleFileStorage $googleFileManager,
        ?array $mandatory = null
    ) {
        $this->firestoreClient   = $firestoreClient;
        $this->mandatory         = $mandatory;
        $this->googleFileManager = $googleFileManager;
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
            //$this->logger->alert();
            return false;
        }

        $success = $this->firestoreClient->runTransaction(
            fn (Transaction $t) => $this->putMetaData($t, $anwendungName, $anwendungId, $uuid, ...$field)
        );

        if (! $success) {
            $this->googleFileManager->delete($uuid);
        }

        return $success;
    }

    /**
     * @internal please use this method internal only !!!
     */
    public function putMetaData(Transaction $t, string $anwendungName, string $anwendungId, UuidInterface $uuid, string ...$field): bool
    {
        $document = $this->firestoreClient->collection($anwendungName)->document($anwendungId);

//        $snapshot = $t->snapshot($document);
        $t->set($document, ['googleFileName', $uuid->toString()]);
return true;
        /*$document->set(['googleFileName', $uuid->toString()]);
        $document->set(['anwendungId', $anwendungId]);

        foreach ($field as $value) {
            $fieldDefinition = \explode(':', $value);

            if (\count($fieldDefinition) < 2) {
                throw new \InvalidArgumentException('Expected in form fieldName:fieldValue given ' . $value . '.');
            }

            $document->set([$fieldDefinition[0], $fieldDefinition[1]]);
        }
        */
    }
}
