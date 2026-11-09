<?php declare(strict_types=1);

namespace Tests\Nyholm\Psr7\Integration;

use Http\Psr7Test\UploadedFileIntegrationTest;
use Nyholm\Psr7\Factory\Psr17_Factory;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\UploadedFileInterface;

class Uploaded_File_Test extends UploadedFileIntegrationTest
{
    public function createSubject(): UploadedFileInterface
    {
        return (new Psr17_Factory())->createUploadedFile(Stream::create('writing to tempfile'));
    }
}
