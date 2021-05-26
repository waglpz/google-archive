<?php

declare(strict_types=1);

include 'vendor/autoload.php';

$config = [
    'projectId'   => 'de-ist-vwd-datadesk',
    'keyFilePath' => __DIR__ . '/cred.json',
];
$gcStorageClient = new \Google\Cloud\Storage\StorageClient($config);
$bucketName = 's3_test_bucket';
$gcFileManager = new \Waglpz\GcloudArchiv\GoogleFileManager($gcStorageClient, $bucketName);
$fireStoreClient = new \Google\Cloud\Firestore\FirestoreClient($config);

$archivator = new \Waglpz\GcloudArchiv\Archivator($fireStoreClient, $gcFileManager);

$content = \base64_encode('Das ist Ein Test');
$ergebniss = $archivator->putFile($content, 'Simple example test', '1234567890');

if ($ergebniss === true) {
    echo "file was uploaded ";
} else {
    echo "File was not uploaded :(";
}

// todo implement
// $datei = $archivator->getFile('Simple example test', '1234567890');

echo PHP_EOL;