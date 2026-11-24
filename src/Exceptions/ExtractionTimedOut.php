<?php

/*
 * This file is part of the Viterbit vbApp package.
 *
 * (c) Viterbit <contact@viterbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Viterbit\PdfImagesExtractor\Exceptions;

/**
 * pdfimages did not finish within the timeout and was killed. Some PDFs make it loop forever.
 */
class ExtractionTimedOut extends CouldNotExtractImages
{
}
