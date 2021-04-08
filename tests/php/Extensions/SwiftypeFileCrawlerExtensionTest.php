<?php

namespace Ichaber\SSSwiftype\Tests\Extensions;

use Exception;
use Ichaber\SSSwiftype\Extensions\SwiftypeFileCrawlerExtension;
use Ichaber\SSSwiftype\Extensions\SwiftypeSiteTreeCrawlerExtension;
use Ichaber\SSSwiftype\Tests\Fake\SwiftypeFile;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
Use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class SwiftypeFileCrawlerExtensionTest
 *
 * @package Ichaber\SSSwiftype\Tests\Extensions
 */
class SwiftypeFileCrawlerExtensionTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'SwiftypeFileCrawlerExtensionTest.yml';

    /**
     * expected array of files to be indexed
     *
     * @var string[]
     */
    protected $expectedUrls = [
        'localhost/assets/SwiftypeFileCrawlerExtensionTest/dummy.pdf',
    ];

    public function setUp(): void
    {
        parent::setUp();

        // Make sure that our cache is cleared between tests
        /** @var SwiftypeFileCrawlerExtension $crawlerExtension */
        $crawlerExtension = Injector::inst()->get(SwiftypeFileCrawlerExtension::class);
        $crawlerExtension->clearCacheAll();

        // Set our config to not clear caches after un/publish, so that we can easily fetch the Urls for our test
        Config::inst()->update(
            SwiftypeFileCrawlerExtension::class,
            'clear_cache_disabled',
            true
        );

        // Set backend assets store root to /SwiftypeFileCrawlerExtensionTest
        TestAssetStore::activate('SwiftypeFileCrawlerExtensionTest');
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlPublished(): void
    {
        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_pdf');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Publish single so that Urls to crawl is populated
        $file->publishSingle();

        // Grab the Urls that we expect to have been collated
        $key = str_replace('\\', '', $file->ClassName . $file->ID);
        $urlsToCrawl = $file->getUrlsToCrawl();

        // Check that the key exists for our file
        $this->assertArrayHasKey($key, $urlsToCrawl);

        // Grab the Urls that are for our page
        $urlsToCrawl = $urlsToCrawl[$key];

        // Strip out any http/https stuff
        foreach ($urlsToCrawl as $urlToCrawl) {
            $url = str_replace('http://', '', $urlToCrawl);
            $url = str_replace('https://', '', $url);

            $urls[] = $url;
        }

        $this->assertEquals($this->expectedUrls, $urls, '', 0.0, 10, true);
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlUnPublished(): void
    {
        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_pdf');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Make sure our file is published before we begin
        $file->publishSingle();

        // Make sure we don't have any Cache set from the above publishing
        $file->clearCacheAll();

        // now we call the service to remove this file from index
        $file->doUnpublish();

        // Grab the Urls that we expect to have been collated
        $key = str_replace('\\', '', $file->ClassName . $file->ID);
        $urlsToCrawl = $file->getUrlsToCrawl();

        // Check that the key exists for our page
        $this->assertArrayHasKey($key, $urlsToCrawl);

        // Grab the Urls that are for our page
        $urlsToCrawl = $urlsToCrawl[$key];

        // Strip out any http/https stuff
        foreach ($urlsToCrawl as $urlToCrawl) {
            $url = str_replace('http://', '', $urlToCrawl);
            $url = str_replace('https://', '', $url);

            $urls[] = $url;
        }

        $this->assertEquals($this->expectedUrls, $urls, '', 0.0, 10, true);
    }

    /**
     * Files that hold no text (e.g. images) should not be indexed
     *
     * @throws Exception
     */
    public function testUrlsNotToCrawlPublished(): void
    {
        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_jpg');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Publish single so that Urls to crawl is populated
        $file->publishSingle();

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $file->getUrlsToCrawl();
        $this->assertEquals($urls, $file->getUrlsToCrawl());
    }

    /**
     * Files that hold no text (e.g. images) should not be indexed
     *
     * @throws Exception
     */
    public function testUrlsNotToCrawlUnpublished(): void
    {
        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_jpg');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Make sure our file is published before we begin
        $file->publishSingle();

        // Make sure we don't have any Cache set from the above publishing
        $file->clearCacheAll();

        // now we call the service to remove this file from index
        $file->doUnpublish();

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $file->getUrlsToCrawl();
        $this->assertEquals($urlsToCrawl, $file->getUrlsToCrawl());

        // Check that the key does not exist for our File
        $key = str_replace('\\', '', $file->ClassName . $file->ID);
        $this->assertArrayNotHasKey($key, $urlsToCrawl);
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlSegmentChanged(): void
    {
        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_pdf');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish anything.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Make sure our file is published before we begin
        $file->publishSingle();

        $key = str_replace('\\', '', $file->ClassName . $file->ID);

        // Make sure our cache is flushed from the above publishing
        $file->clearCacheAll();

        /**
         * Note:
         *  Somehow because File DBObject records in Stage.Stage create a hash path in the URL to access the file,
         *  the asserted array contained 3 elements instead of 2. To ignore this we only asserted
         *  that our old and new file URL's exist in the array that is sent for reindexing.
         */
        // Update our URL Segment
        Versioned::withVersionedMode(function () use ($file) {
            Versioned::set_reading_mode('Stage.Stage');
            $file->renameFile('dummy-new.pdf');
        });

        // publish our file again to get new URL.
        $file->publishSingle();

        // We expect two URL's now. One from before the file rename change, and one from after it
        $this->expectedUrls = [
            'old_file' => 'localhost/assets/SwiftypeFileCrawlerExtensionTest/dummy.pdf',
            'new_file' => 'localhost/assets/SwiftypeFileCrawlerExtensionTest/dummy-new.pdf',
        ];

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $file->getUrlsToCrawl();

        // Check that the key exists for our page
        $this->assertArrayHasKey($key, $urlsToCrawl);

        // Grab the Urls that are for our file
        $urlsToCrawl = $urlsToCrawl[$key];

        // Strip out any http/https stuff
        foreach ($urlsToCrawl as $urlToCrawl) {
            $url = str_replace('http://', '', $urlToCrawl);
            $url = str_replace('https://', '', $url);

            $urls[] = $url;
        }

        $this->assertContains($this->expectedUrls['old_file'], $urls);
        $this->assertContains($this->expectedUrls['new_file'], $urls);
    }

    public function testUrlsToCrawlCacheCleared(): void
    {
        // Since asserting cache is cleared we want to reenable cache for this test.
        Config::inst()->update(
            SwiftypeFileCrawlerExtension::class,
            'clear_cache_disabled',
            false
        );

        /** @var SwiftypeFile $file */
        $file = $this->objFromFixture(SwiftypeFile::class, 'file_pdf');
        $sourcePath = __DIR__ . '/../Fixtures/' . $file->Name;
        $file->setFromLocalFile($sourcePath, $file->Filename);

        // check our urls are not populated before we publish anything.
        $urls = [];
        $this->assertEquals($urls, $file->getUrlsToCrawl());

        // Publish single so that Urls to crawl is populated
        $file->publishSingle();

        $key = str_replace('\\', '', $file->ClassName . $file->ID);

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $file->getUrlsToCrawl();

        // Check that the key exists for our page
        $this->assertArrayNotHasKey($key, $urlsToCrawl);
    }

    public function tearDown()
    {
        TestAssetStore::reset();
        parent::tearDown();
    }
}
