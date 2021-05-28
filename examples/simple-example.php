<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

include 'vendor/autoload.php';

$config = [
    'projectId'   => 'de-ist-vwd-datadesk',
    'keyFilePath' => __DIR__ . '/cred.json',
];

$requiredFields = [
    'a' => true,
    'z' => true,
];

$loggerFactory = new \MonologFactory\LoggerFactory();

$logger = $loggerFactory->create('my_logger', [
    'handlers'   => [
        [
            'name'         => SyslogHandler::class,
            'params'       => [
                'ident'    => 'onlinemsg',
                'facility' => 'local0',
                'level'    => Logger::DEBUG,
            ],
        ],
        [
            'name'       => StreamHandler::class,
            'params'     => [
                'stream' => 'php://STDOUT',
                'level'  => Logger::DEBUG,
            ],
        ],
    ],
    'processors' => [
        [
            'name' => PsrLogMessageProcessor::class,
        ],
    ],
]);

$gcStorageClient = new \Google\Cloud\Storage\StorageClient($config);
$bucketName = 's3_test_bucket';
$gcFileManager = new \Waglpz\GcloudArchiv\GoogleFileManager($gcStorageClient, $bucketName);
$fireStoreClient = new \Google\Cloud\Firestore\FirestoreClient($config);

$archivator = new \Waglpz\GcloudArchiv\Archivator($fireStoreClient, $fireStoreClient, $gcFileManager, $requiredFields);

$content = \base64_encode('Das ist Ein Test');
$ergebniss = $archivator->putFile($content, 'Simple example test', '1234567890', 'a:AAA', 'b:YYY', 'z:ZZZ');


if ($ergebniss === true) {
    echo "file was uploaded ";
} else {
    echo "File was not uploaded :(";
}

echo PHP_EOL;

$content = $archivator->downloadAsStream('Simple example test', '1234567890')->getContents();

shell_exec("echo " . $content . " > ./1234567890.pdf");


