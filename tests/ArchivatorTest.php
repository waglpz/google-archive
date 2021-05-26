<?php

declare(strict_types=1);

namespace Waglpz\GcloudArchiv\Tests;

use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Waglpz\GcloudArchiv\Archivator;
use Waglpz\GcloudArchiv\GoogleFileStorage;

final class ArchivatorTest extends TestCase
{
    /** @test */
    public function itRollbackIfNotSuccessfullyStoredMetaData(): void
    {
        $uuid            = Uuid::uuid4();
        $firestoreClient = $this->createMock(FirestoreClient::class);
        $firestoreClient->expects(self::once())
                        ->method('runTransaction')
                        ->with(self::isInstanceOf(\Closure::class))
                        ->willReturn(false);
        //$firestoreClient->expects(self::once())->method('collection')->with('TestanwendunsName');

        $googleFileManager = $this->createMock(GoogleFileStorage::class);
        $googleFileManager->expects(self::once())->method('fromBase64')->with('content')->willReturn($uuid);
        $googleFileManager->expects(self::once())->method('delete')->with($uuid);

        $sut  = new Archivator($firestoreClient, $googleFileManager);
        $fact = $sut->putFile('content', 'TestanwendunsName', '123');

        self::assertFalse($fact);
    }

    /** @test */
    public function itReturnsFalseIfExceptionWasThrownAtGoogleStorage(): void
    {
        $firestoreClient = $this->createMock(FirestoreClient::class);
        $firestoreClient->expects(self::never())->method('runTransaction');

        $googleFileManager = $this->createMock(GoogleFileStorage::class);
        $googleFileManager->expects(self::once())
                          ->method('fromBase64')
                          ->with('content')
                          ->willThrowException(new \Error('ERRRRRROR!'));
        $googleFileManager->expects(self::never())->method('delete');

        $sut = new Archivator($firestoreClient, $googleFileManager);

        $fact = $sut->putFile('content', 'TestanwendunsName', '123');

        self::assertFalse($fact);
    }

    /** @test */
    public function itMetaDataWasStoredWithFieldValues(): void
    {
        $uuid = Uuid::uuid4();

        $document = $this->createMock(\Google\Cloud\Firestore\DocumentReference::class);
        $document->expects(self::exactly(4))->method('set')
                 ->withConsecutive(
                     [
                         [
                             'googleFileName',
                             $uuid->toString(),
                         ],
                     ],
                     [
                         [
                             'anwendungId',
                             '123',
                         ],
                     ],
                     [
                         [
                             'a',
                             'b',
                         ],
                     ],
                     [
                         [
                             'y',
                             'z',
                         ],
                     ]
                 );

        $collection = $this->createMock(\Google\Cloud\Firestore\CollectionReference::class);
        $collection->expects(self::once())->method('document')->with('123')->willReturn($document);

        $firestoreClient = $this->createMock(FirestoreClient::class);
        //$firestoreClient = $this->getMockBuilder(FirestoreClient::class)->disableOriginalConstructor()->onlyMethods(['collection'])->getMock();
        //$firestoreClient->expects(self::once())->method('runTransaction')->with(self::isInstanceOf(\Closure::class))->willReturn(false);
        $firestoreClient->expects(self::once())
                        ->method('collection')
                        ->with('TestanwendunsName')
                        ->willReturn($collection);

        $googleFileManager = $this->createMock(GoogleFileStorage::class);
        //$googleFileManager->expects(self::once())->method('fromBase64')->with('content')->willReturn($uuid);
        //$googleFileManager->expects(self::once())->method('delete')->with($uuid);

        $field1 = 'a:b';
        $field2 = 'y:z';
        $sut    = new Archivator($firestoreClient, $googleFileManager);
        $sut->putMetaData('TestanwendunsName', '123', $uuid, $field1, $field2);
    }

    /** @test */
    public function itPutMetaDataThrownException(): void
    {
        $uuid = Uuid::uuid4();

        $document = $this->createMock(\Google\Cloud\Firestore\DocumentReference::class);
        $document->expects(self::exactly(2))->method('set');

        $collection = $this->createMock(\Google\Cloud\Firestore\CollectionReference::class);
        $collection->expects(self::once())->method('document')->with('123')->willReturn($document);

        $firestoreClient = $this->createMock(FirestoreClient::class);
        $firestoreClient->expects(self::once())
                        ->method('collection')
                        ->with('TestanwendunsName')
                        ->willReturn($collection);

        $googleFileManager = $this->createMock(GoogleFileStorage::class);

        $field = 'a';
        $sut   = new Archivator($firestoreClient, $googleFileManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected in form fieldName:fieldValue given a.');
        $sut->putMetaData('TestanwendunsName', '123', $uuid, $field);
    }
}
