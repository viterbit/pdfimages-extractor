<?php

/*
 * This file is part of the Viterbit vbApp package.
 *
 * (c) Viterbit <contact@viterbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Viterbit\PdfImagesExtractor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Viterbit\PdfImagesExtractor\Exceptions\CouldNotExtractImages;
use Viterbit\PdfImagesExtractor\Exceptions\ExtractionTimedOut;
use Viterbit\PdfImagesExtractor\Pdf;

/**
 * Runs Pdf against fake pdfimages binaries: shell scripts that receive the same arguments as the real one,
 * the image root being the last.
 */
final class PdfTest extends TestCase
{
    /** @var string */
    private $directory;

    /** @var string */
    private $pdf;

    /** @var string */
    private $destinationRoot;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pdfimages-extractor-test-'.bin2hex(random_bytes(8));
        $this->destinationRoot = $this->directory.'/destination';
        mkdir($this->destinationRoot, 0700, true);
        $this->pdf = $this->directory.'/cv.pdf';
        file_put_contents($this->pdf, '%PDF-1.7');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testItReturnsTheExtractedImagesAndPassesTheOptionsInOrder(): void
    {
        $arguments = $this->directory.'/arguments';
        $binary = $this->fakePdfimages(sprintf('echo "$@" > %s; : > "${last}-000.ppm"; : > "${last}-001.ppm"', escapeshellarg($arguments)));

        $images = Pdf::getImages($this->pdf, $this->destinationRoot, $binary, ['f 1', 'l 2'], 5);

        $names = [];
        foreach ($images as $image) {
            $names[] = $image->getFilename();
        }
        sort($names);
        $this->assertSame(['-000.ppm', '-001.ppm'], $names);
        $this->assertStringStartsWith(
            '-f 1 -l 2 '.$this->pdf.' '.$this->destinationRoot.'/pdfimages',
            trim((string) file_get_contents($arguments))
        );
    }

    public function testItWorksWithoutATimeoutAsBefore(): void
    {
        $binary = $this->fakePdfimages(': > "${last}-000.ppm"');

        $images = Pdf::getImages($this->pdf, $this->destinationRoot, $binary);

        $this->assertCount(1, iterator_to_array($images));
    }

    public function testItKillsAnExtractionThatWritesImagesForeverAndRemovesThem(): void
    {
        $this->requireTimeoutCommand();
        $binary = $this->fakePdfimages('i=0; while :; do i=$((i+1)); : > "${last}-${i}.pbm"; done');

        $start = microtime(true);
        $exception = $this->extractExpectingFailure($binary, 1);
        $elapsed = microtime(true) - $start;

        $this->assertInstanceOf(ExtractionTimedOut::class, $exception);
        $this->assertLessThan(1 + 5, $elapsed);
        $this->assertDestinationRootIsEmpty();
    }

    public function testItKillsAnExtractionThatNeverEnds(): void
    {
        $this->requireTimeoutCommand();
        $binary = $this->fakePdfimages('exec sleep 600');

        $start = microtime(true);
        $exception = $this->extractExpectingFailure($binary, 1);
        $elapsed = microtime(true) - $start;

        $this->assertInstanceOf(ExtractionTimedOut::class, $exception);
        $this->assertLessThan(1 + 5, $elapsed);
        $this->assertDestinationRootIsEmpty();
    }

    public function testTheKilledPdfimagesStopsWriting(): void
    {
        $this->requireTimeoutCommand();
        $counter = $this->directory.'/counter';
        $binary = $this->fakePdfimages(sprintf('i=0; while :; do i=$((i+1)); echo $i > %s; done', escapeshellarg($counter)));

        $this->extractExpectingFailure($binary, 1);
        $countAfterKill = (string) file_get_contents($counter);
        usleep(500000);

        $this->assertSame($countAfterKill, (string) file_get_contents($counter));
    }

    public function testItRemovesTheImagesWhenTheExtractionFails(): void
    {
        $binary = $this->fakePdfimages(': > "${last}-000.ppm"; exit 1');

        $exception = $this->extractExpectingFailure($binary, 5);

        $this->assertInstanceOf(CouldNotExtractImages::class, $exception);
        $this->assertNotInstanceOf(ExtractionTimedOut::class, $exception);
        $this->assertDestinationRootIsEmpty();
    }

    public function testItRejectsATimeoutBelowOneSecond(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Pdf())->setTimeout(0);
    }

    private function fakePdfimages(string $body): string
    {
        $path = $this->directory.'/fake-pdfimages';
        file_put_contents($path, "#!/bin/sh\nfor last; do :; done\n".$body."\n");
        chmod($path, 0755);

        return $path;
    }

    private function extractExpectingFailure(string $binary, int $timeout): CouldNotExtractImages
    {
        try {
            Pdf::getImages($this->pdf, $this->destinationRoot, $binary, [], $timeout);
        } catch (CouldNotExtractImages $exception) {
            return $exception;
        }

        $this->fail('The extraction was expected to fail.');
    }

    private function assertDestinationRootIsEmpty(): void
    {
        $this->assertSame([], array_values(array_diff((array) scandir($this->destinationRoot), ['.', '..'])));
    }

    private function requireTimeoutCommand(): void
    {
        if (null === (new ExecutableFinder())->find('timeout')) {
            $this->markTestSkipped('The timeout command is not available.');
        }
    }
}
