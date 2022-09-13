<?php

declare(strict_types=1);

namespace LaminasTest\Validator\File;

use Laminas\Validator\Exception\InvalidArgumentException;
use Laminas\Validator\File;
use PHPUnit\Framework\TestCase;

use function basename;
use function current;
use function dirname;
use function is_array;

use const UPLOAD_ERR_NO_FILE;

/**
 * @group      Laminas_Validator
 */
class ExistsTest extends TestCase
{
    /**
     * @psalm-return array<array-key, array{
     *     0: string,
     *     1: string|array{
     *         tmp_name: string,
     *         name: string,
     *         size: int,
     *         error: int,
     *         type: string
     *     },
     *     2: bool
     * }>
     */
    public function basicBehaviorDataProvider(): array
    {
        $testFile   = __DIR__ . '/_files/testsize.mo';
        $baseDir    = dirname($testFile);
        $baseName   = basename($testFile);
        $fileUpload = [
            'tmp_name' => $testFile,
            'name'     => basename($testFile),
            'size'     => 200,
            'error'    => 0,
            'type'     => 'text',
        ];
        return [
            //    Options, isValid Param, Expected value
            [dirname($baseDir), $baseName,   false],
            [$baseDir,          $baseName,   true],
            [$baseDir,          $testFile,   true],
            [dirname($baseDir), $fileUpload, false],
            [$baseDir,          $fileUpload, true],
        ];
    }

    /**
     * Ensures that the validator follows expected behavior
     *
     * @dataProvider basicBehaviorDataProvider
     * @param string|array $isValidParam
     */
    public function testBasic(string $options, $isValidParam, bool $expected): void
    {
        $validator = new File\Exists($options);
        $this->assertEquals($expected, $validator->isValid($isValidParam));
    }

    /**
     * Ensures that the validator follows expected behavior for legacy Laminas\Transfer API
     *
     * @dataProvider basicBehaviorDataProvider
     * @param string|array $isValidParam
     */
    public function testLegacy(string $options, $isValidParam, bool $expected): void
    {
        if (! is_array($isValidParam)) {
            $this->markTestSkipped('An array is expected for legacy compat tests');
        }

        $validator = new File\Exists($options);
        $this->assertEquals($expected, $validator->isValid($isValidParam['tmp_name'], $isValidParam));
    }

    /**
     * Ensures that getDirectory() returns expected value
     *
     * @return void
     */
    public function testGetDirectory()
    {
        $validator = new File\Exists('C:/temp');
        $this->assertEquals('C:/temp', $validator->getDirectory());

        $validator = new File\Exists(['temp', 'dir', 'jpg']);
        $this->assertEquals('temp,dir,jpg', $validator->getDirectory());

        $validator = new File\Exists(['temp', 'dir', 'jpg']);
        $this->assertEquals(['temp', 'dir', 'jpg'], $validator->getDirectory(true));
    }

    /**
     * Ensures that setDirectory() returns expected value
     *
     * @return void
     */
    public function testSetDirectory()
    {
        $validator = new File\Exists('temp');
        $validator->setDirectory('gif');
        $this->assertEquals('gif', $validator->getDirectory());
        $this->assertEquals(['gif'], $validator->getDirectory(true));

        $validator->setDirectory('jpg, temp');
        $this->assertEquals('jpg,temp', $validator->getDirectory());
        $this->assertEquals(['jpg', 'temp'], $validator->getDirectory(true));

        $validator->setDirectory(['zip', 'ti']);
        $this->assertEquals('zip,ti', $validator->getDirectory());
        $this->assertEquals(['zip', 'ti'], $validator->getDirectory(true));
    }

    /**
     * Ensures that addDirectory() returns expected value
     *
     * @return void
     */
    public function testAddDirectory()
    {
        $validator = new File\Exists('temp');
        $validator->addDirectory('gif');
        $this->assertEquals('temp,gif', $validator->getDirectory());
        $this->assertEquals(['temp', 'gif'], $validator->getDirectory(true));

        $validator->addDirectory('jpg, to');
        $this->assertEquals('temp,gif,jpg,to', $validator->getDirectory());
        $this->assertEquals(['temp', 'gif', 'jpg', 'to'], $validator->getDirectory(true));

        $validator->addDirectory(['zip', 'ti']);
        $this->assertEquals('temp,gif,jpg,to,zip,ti', $validator->getDirectory());
        $this->assertEquals(['temp', 'gif', 'jpg', 'to', 'zip', 'ti'], $validator->getDirectory(true));

        $validator->addDirectory('');
        $this->assertEquals('temp,gif,jpg,to,zip,ti', $validator->getDirectory());
        $this->assertEquals(['temp', 'gif', 'jpg', 'to', 'zip', 'ti'], $validator->getDirectory(true));
    }

    /**
     * @group Laminas-11258
     */
    public function testLaminas11258(): void
    {
        $validator = new File\Exists(__DIR__);
        $this->assertFalse($validator->isValid('nofile.mo'));
        $this->assertArrayHasKey('fileExistsDoesNotExist', $validator->getMessages());
        $this->assertStringContainsString('does not exist', current($validator->getMessages()));
    }

    public function testEmptyFileArrayShouldReturnFalse(): void
    {
        $validator = new File\Exists();

        $this->assertFalse($validator->isValid(''));
        $this->assertArrayHasKey(File\Exists::DOES_NOT_EXIST, $validator->getMessages());

        $filesArray = [
            'name'     => '',
            'size'     => 0,
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'type'     => '',
        ];

        $this->assertFalse($validator->isValid($filesArray));
        $this->assertArrayHasKey(File\Exists::DOES_NOT_EXIST, $validator->getMessages());
    }

    public function testIsValidShouldThrowInvalidArgumentExceptionForArrayNotInFilesFormat(): void
    {
        $validator = new File\Exists();
        $value     = ['foo' => 'bar'];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value array must be in $_FILES format');
        $validator->isValid($value);
    }

    /**
     * @psalm-return array<string, array{0: mixed}>
     */
    public function invalidDirectoryArguments(): array
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'object'     => [(object) []],
        ];
    }

    /**
     * @dataProvider invalidDirectoryArguments
     * @param mixed $value
     */
    public function testAddDirectoryShouldRaiseExceptionForInvalidArgument($value): void
    {
        $validator = new File\Exists();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid options to validator provided');
        $validator->addDirectory($value);
    }
}
