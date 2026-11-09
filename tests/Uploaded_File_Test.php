<?php

namespace Tests\Nyholm\Psr7;

use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Uploaded_File;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Nyholm\Psr7\Uploaded_File
 */
class Uploaded_File_Test extends TestCase
{
    protected $cleanup;

    protected function setUp(): void
    {
        $this->cleanup = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $file) {
            if (\is_scalar($file) && \file_exists($file)) {
                \unlink($file);
            }
        }
    }

    public static function invalid_streams()
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'array' => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalid_streams
     */
    public function test_raises_exception_on_invalid_stream_or_file($stream_or_file)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid stream or file provided for Uploaded_File');

        new Uploaded_File($stream_or_file, 0, \UPLOAD_ERR_OK);
    }

    public static function invalid_error_statuses()
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'float' => [1.1],
            'string' => ['1'],
            'array' => [[1]],
            'object' => [(object) [1]],
            'negative' => [-1],
            'too-big' => [9],
        ];
    }

    /**
     * @dataProvider invalid_error_statuses
     */
    public function test_raises_exception_on_invalid_error_status($status)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('status');

        new Uploaded_File(\fopen('php://temp', 'wb+'), 0, $status);
    }

    public static function invalid_filenames_and_media_types()
    {
        return [
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'array' => [['string']],
            'object' => [(object) ['string']],
        ];
    }

    /**
     * @dataProvider invalid_filenames_and_media_types
     */
    public function test_raises_exception_on_invalid_client_filename($filename)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename');

        new Uploaded_File(\fopen('php://temp', 'wb+'), 0, \UPLOAD_ERR_OK, $filename);
    }

    /**
     * @dataProvider invalid_filenames_and_media_types
     */
    public function test_raises_exception_on_invalid_client_media_type($media_type)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('media type');

        new Uploaded_File(\fopen('php://temp', 'wb+'), 0, \UPLOAD_ERR_OK, 'foobar.baz', $media_type);
    }

    public function test_get_stream_returns_original_stream_object()
    {
        $stream = Stream::create('');
        $upload = new Uploaded_File($stream, 0, \UPLOAD_ERR_OK);

        $this->assertSame($stream, $upload->getStream());
    }

    public function test_get_stream_returns_wrapped_php_stream()
    {
        $stream = \fopen('php://temp', 'wb+');
        $upload = new Uploaded_File($stream, 0, \UPLOAD_ERR_OK);
        $upload_stream = $upload->getStream()->detach();

        $this->assertSame($stream, $upload_stream);
    }

    public function test_get_stream()
    {
        $upload = new Uploaded_File(__DIR__ . '/Resources/foo.txt', 0, \UPLOAD_ERR_OK);
        $stream = $upload->getStream();
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals('Foobar' . \PHP_EOL, $stream->__toString());
    }

    public function test_successful()
    {
        $stream = Stream::create('Foo bar!');
        $upload = new Uploaded_File($stream, $stream->getSize(), \UPLOAD_ERR_OK, 'filename.txt', 'text/plain');

        $this->assertEquals($stream->getSize(), $upload->getSize());
        $this->assertEquals('filename.txt', $upload->getClientFilename());
        $this->assertEquals('text/plain', $upload->getClientMediaType());

        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'successful');
        $upload->moveTo($to);
        $this->assertFileExists($to);
        $this->assertEquals($stream->__toString(), \file_get_contents($to));
    }

    public static function invalid_move_paths()
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'empty' => [''],
            'array' => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalid_move_paths
     */
    public function test_move_raises_exception_for_invalid_path($path)
    {
        $stream = (new \Nyholm\Psr7\Factory\Psr17_Factory())->createStream('Foo bar!');
        $upload = new Uploaded_File($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $path;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');
        $upload->moveTo($path);
    }

    public function test_move_cannot_be_called_more_than_once()
    {
        $stream = (new \Nyholm\Psr7\Factory\Psr17_Factory())->createStream('Foo bar!');
        $upload = new Uploaded_File($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        $this->assertTrue(\file_exists($to));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->moveTo($to);
    }

    public function test_cannot_retrieve_stream_after_move()
    {
        $stream = (new \Nyholm\Psr7\Factory\Psr17_Factory())->createStream('Foo bar!');
        $upload = new Uploaded_File($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        $this->assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->getStream();
    }

    public static function non_ok_error_status()
    {
        return [
            'UPLOAD_ERR_INI_SIZE' => [\UPLOAD_ERR_INI_SIZE],
            'UPLOAD_ERR_FORM_SIZE' => [\UPLOAD_ERR_FORM_SIZE],
            'UPLOAD_ERR_PARTIAL' => [\UPLOAD_ERR_PARTIAL],
            'UPLOAD_ERR_NO_FILE' => [\UPLOAD_ERR_NO_FILE],
            'UPLOAD_ERR_NO_TMP_DIR' => [\UPLOAD_ERR_NO_TMP_DIR],
            'UPLOAD_ERR_CANT_WRITE' => [\UPLOAD_ERR_CANT_WRITE],
            'UPLOAD_ERR_EXTENSION' => [\UPLOAD_ERR_EXTENSION],
        ];
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_constructor_does_not_raise_exception_for_invalid_stream_when_error_status_present($status)
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        $this->assertSame($status, $uploaded_file->getError());
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_move_to_raises_exception_when_error_status_present($status)
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploaded_file->moveTo(__DIR__ . '/' . \uniqid());
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_get_stream_raises_exception_when_error_status_present($status)
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploaded_file->getStream();
    }

    public function test_move_to_creates_stream_if_only_a_filename_was_provided()
    {
        $this->cleanup[] = $from = \tempnam(\sys_get_temp_dir(), 'copy_from');
        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'copy_to');

        \copy(__FILE__, $from);

        $uploaded_file = new Uploaded_File($from, 100, \UPLOAD_ERR_OK, \basename($from), 'text/plain');
        $uploaded_file->moveTo($to);

        $this->assertFileEquals(__FILE__, $to);
    }
}
