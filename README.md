# Extract images from a pdf

This package provides a class to extract images from a pdf.

```php
use Viterbit\PdfImagesExtractor\Pdf;

echo Pdf::getImages('book.pdf'); //returns the text from the pdf
```

## Requirements

Behind the scenes this package leverages [pdfimages](https://en.wikipedia.org/wiki/Pdfimages). You can verify if the binary installed on your system by issueing this command:

```bash
which pdfimages
```

If it is installed it will return the path to the binary.

To install the binary you can use this command on Ubuntu or Debian:

```bash
apt-get install poppler-utils
```

On a mac you can install the binary using brew

```bash
brew install poppler
```

If you're on RedHat or CentOS use this:

```bash
yum install poppler-utils
```

## Installation

You can install the package via composer:

```bash
composer viterbit/pdfimages-extractor
```

## Usage

Extracting text from a pdf is easy.

```php
$text = (new Pdf())
    ->setPdf('book.pdf')
    ->images();
```

Or easier:

```php
echo Pdf::getImages('book.pdf');
```

By default the package will assume that the `pdftoimages` command is located at `/usr/bin/pdftoimages`.
If it is located elsewhere pass its binary path to constructor

```php
$text = (new Pdf('/custom/path/to/pdftoimages'))
    ->setPdf('book.pdf')
    ->images();
```

or as the second parameter to the `getImages` static method:

```php
echo Pdf::getText('book.pdf', '/custom/path/to/pdftoimages');
```

Sometimes you may want to use [pdfimages options](https://linux.die.net/man/1/pdfimages). To do so you can set them up using the `setOptions` method.

```php
$text = (new Pdf())
    ->setPdf('book.pdf')
    ->setOptions(['j', 'f 1'])
    ->images()
;
```

or as the third parameter to the `getImages` static method:

```php
echo Pdf::getImages('book.pdf', null, ['j', 'f 1']);
```

Please note that successive calls to `setOptions()` will overwrite options passed in during previous calls.

If you need to make multiple calls to add options (for example if you need to pass in default options when creating
the `Pdf` object from a container, and then add context-specific options elsewhere), you can use the `addOptions()` method:

 ```php
 $text = (new Pdf())
     ->setPdf('book.pdf')
     ->setOptions(['layout', 'r 96'])
     ->addOptions(['f 1'])
     ->images()
 ;
 ```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information about what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email freek@spatie.be instead of using the issue tracker.
design agency based in Antwerp, Belgium. You'll find an overview of all our open source projects [on our website](https://spatie.be/opensource).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
