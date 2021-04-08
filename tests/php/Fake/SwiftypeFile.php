<?php

namespace Ichaber\SSSwiftype\Tests\Fake;

use Ichaber\SSSwiftype\Extensions\SwiftypeMetaTagContentExtension;
use Ichaber\SSSwiftype\Extensions\SwiftypeFileCrawlerExtension;
use Ichaber\SSSwiftype\Tests\Extensions\SwiftypeMetaTagContentExtensionTest;
use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;

/**
 * Class SwiftypeFile
 *
 * @package Ichaber\SSSwiftype\Tests\Fake
 * @mixin SwiftypeMetaTagContentExtension
 * @mixin SwiftypeFileCrawlerExtension
 */
class SwiftypeFile extends File implements TestOnly
{
    /**
     * @var array
     */
    private static $extensions = [
        SwiftypeMetaTagContentExtension::class,
        SwiftypeFileCrawlerExtension::class,
    ];

    /**
     * config setting to whitelist which files can be indexed.
     * Defaults to 'pdf' for our unit tests.
     *
     * @var string[]
     */
    private static $reindex_files_whitelist = ['pdf'];

    /**
     * This needs to be set in your test.
     *
     * @var array
     * @see SwiftypeMetaTagContentExtensionTest::testMetaTagOutput() for an example
     */
    private static $swiftype_meta_tag_classes = [];
}
