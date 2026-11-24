<?php

/*
 * This file is part of the Viterbit vbApp package.
 *
 * (c) Viterbit <contact@viterbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Viterbit\PdfImagesExtractor;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Viterbit\PdfImagesExtractor\Exceptions\CouldNotExtractImages;
use Viterbit\PdfImagesExtractor\Exceptions\DestinationNotFound;
use Viterbit\PdfImagesExtractor\Exceptions\DestinationNotWritable;
use Viterbit\PdfImagesExtractor\Exceptions\ExtractionTimedOut;
use Viterbit\PdfImagesExtractor\Exceptions\PdfNotFound;

final class Pdf
{
    public const DEFAULT_TIMEOUT = 60;

    /** Exit code of a process killed with SIGKILL, which is what `timeout -s KILL` does when time runs out. */
    private const KILLED_EXIT_CODE = 137;

    /** Seconds Symfony Process waits beyond the timeout, in case the `timeout` command could not kill pdfimages. */
    private const PROCESS_TIMEOUT_MARGIN = 5;

    protected $pdf;
    protected $destinationFolder;

    protected $binPath;

    protected $options = [];

    protected $timeout = self::DEFAULT_TIMEOUT;

    public function __construct(?string $binPath = null)
    {
        $this->binPath = $binPath ?? '/usr/bin/pdfimages';
    }

    public function setPdf(string $pdf): self
    {
        if (!is_readable($pdf)) {
            throw new PdfNotFound("Could not read `{$pdf}`");
        }

        $this->pdf = $pdf;

        return $this;
    }

    public function setDestinationFolder(?string $destinationRootFolder = null): self
    {
        $destinationRootFolder = $destinationRootFolder ?? sys_get_temp_dir();
        if (false === is_dir($destinationRootFolder)) {
            throw new DestinationNotFound(sprintf('Destination folder "%s" not found', $destinationRootFolder));
        }

        if (false === is_writable($destinationRootFolder)) {
            throw new DestinationNotWritable(sprintf('Destination folder "%s" is not writable', $destinationRootFolder));
        }

        $this->destinationFolder = $destinationRootFolder.'/'.uniqid('pdfimages', true).'/';
        if (!mkdir($concurrentDirectory = $this->destinationFolder) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this;
    }

    public function setOptions(array $options): self
    {
        $this->options = $this->parseOptions($options);

        return $this;
    }

    public function addOptions(array $options): self
    {
        $this->options = array_merge(
            $this->options,
            $this->parseOptions($options)
        );

        return $this;
    }

    /**
     * Seconds pdfimages may run before it is killed. When the `timeout` command exists, the system kills it,
     * so the limit holds even if the PHP process that launched it dies first.
     */
    public function setTimeout(int $seconds): self
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException(sprintf('The timeout must be at least 1 second, %d given', $seconds));
        }

        $this->timeout = $seconds;

        return $this;
    }

    /**
     * @throws ExtractionTimedOut    when pdfimages does not finish within the timeout
     * @throws CouldNotExtractImages when pdfimages fails
     *
     * In both cases the destination folder and the images already written are removed
     */
    public function images(): \FilesystemIterator
    {
        $timeoutBinPath = (new ExecutableFinder())->find('timeout');
        $process = new Process($this->command($timeoutBinPath));
        $process->setTimeout($this->timeout + self::PROCESS_TIMEOUT_MARGIN);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $this->removeDestinationFolder();

            throw new ExtractionTimedOut($process);
        } catch (RuntimeException $exception) {
            // `timeout -s KILL` kills pdfimages and then itself with the same signal, which Process reports
            // by throwing; the exit code tells it apart below.
            if (!$process->hasBeenSignaled()) {
                $this->removeDestinationFolder();

                throw $exception;
            }
        }

        if (!$process->isSuccessful()) {
            $this->removeDestinationFolder();

            if (null !== $timeoutBinPath && self::KILLED_EXIT_CODE === $process->getExitCode()) {
                throw new ExtractionTimedOut($process);
            }

            throw new CouldNotExtractImages($process);
        }

        return new \FilesystemIterator($this->destinationFolder, \FilesystemIterator::SKIP_DOTS);
    }

    public static function getImages(
        string $pdf,
        ?string $destinationRootFolder = null,
        ?string $binPath = null,
        array $options = [],
        ?int $timeout = null
    ): \FilesystemIterator {
        return (new static($binPath))
            ->setOptions($options)
            ->setTimeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->setPdf($pdf)
            ->setDestinationFolder($destinationRootFolder)
            ->images()
        ;
    }

    protected function parseOptions(array $options): array
    {
        $mapper = function (string $content): array {
            $content = trim($content);
            if ('-' !== ($content[0] ?? '')) {
                $content = '-'.$content;
            }

            return explode(' ', $content, 2);
        };

        $reducer = function (array $carry, array $option): array {
            return array_merge($carry, $option);
        };

        return array_reduce(array_map($mapper, $options), $reducer, []);
    }

    private function command(?string $timeoutBinPath): array
    {
        $command = array_merge([$this->binPath], $this->options, [$this->pdf, $this->destinationFolder]);

        if (null === $timeoutBinPath) {
            return $command;
        }

        return array_merge([$timeoutBinPath, '-s', 'KILL', (string) $this->timeout], $command);
    }

    /**
     * Removes the folder created by setDestinationFolder() for this extraction, one file at a time:
     * a looping pdfimages can leave millions of images, too many to list in memory.
     */
    private function removeDestinationFolder(): void
    {
        if (null === $this->destinationFolder || !is_dir($this->destinationFolder)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->destinationFolder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->destinationFolder);
    }
}
