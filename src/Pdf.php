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

use Symfony\Component\Process\Process;
use Viterbit\PdfImagesExtractor\Exceptions\CouldNotExtractImages;
use Viterbit\PdfImagesExtractor\Exceptions\DestinationNotFound;
use Viterbit\PdfImagesExtractor\Exceptions\DestinationNotWritable;
use Viterbit\PdfImagesExtractor\Exceptions\PdfNotFound;

final class Pdf
{
    protected $pdf;
    protected $destinationFolder;

    protected $binPath;

    protected $options = [];

    public function __construct(string $binPath = null)
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

    public function setDestinationFolder(string $destinationRootFolder = null): self
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

    public function images(): \FilesystemIterator
    {
        $process = new Process(array_merge([$this->binPath], $this->options, [$this->pdf, $this->destinationFolder]));
        $process->run();
        if (!$process->isSuccessful()) {
            throw new CouldNotExtractImages($process);
        }

        return new \FilesystemIterator($this->destinationFolder, \FilesystemIterator::SKIP_DOTS);
    }

    public static function getImages(string $pdf, string $destinationRootFolder = null, string $binPath = null, array $options = []): \FilesystemIterator
    {
        return (new static($binPath))
            ->setOptions($options)
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
}
