<?php

namespace Ichaber\SSSwiftype\Tests\Extensions;

use Exception;
use Ichaber\SSSwiftype\Extensions\SwiftypeSiteTreeCrawlerExtension;
use Ichaber\SSSwiftype\Tests\Fake\SwiftypeSiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Class SwiftypeMetaTagContentExtensionTest
 *
 * @package Ichaber\SSSwiftype\Tests\Extensions
 */
class SwiftypeSiteTreeCrawlerExtensionTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'SwiftypeSiteTreeCrawlerExtensionTest.yml';

    public function setUp(): void
    {
        parent::setUp();

        // Make sure that our cache is cleared between tests
        /** @var SwiftypeSiteTreeCrawlerExtension $crawlerExtension */
        $crawlerExtension = Injector::inst()->get(SwiftypeSiteTreeCrawlerExtension::class);
        $crawlerExtension->clearCacheAll();
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlPublished(): void
    {
        // Set our config to not clear caches after un/publish, so that we can easily fetch the Urls for our test
        Config::inst()->update(
            SwiftypeSiteTreeCrawlerExtension::class,
            'clear_cache_disabled',
            true
        );

        /** @var SwiftypeSiteTree $page */
        $page = $this->objFromFixture(SwiftypeSiteTree::class, 'page1');

        // Publish single so that Urls to crawl is populated
        $page->publishSingle();
        $key = str_replace('\\', '', $page->ClassName . $page->ID);

        $expectedUrls = [
            'localhost/page1/',
        ];
        $urls = [];

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $page->getUrlsToCrawl();

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

        $this->assertEquals($expectedUrls, $urls, '', 0.0, 10, true);
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlUnpublished(): void
    {
        // Set our config to not clear caches after un/publish, so that we can easily fetch the Urls for our test
        Config::inst()->update(
            SwiftypeSiteTreeCrawlerExtension::class,
            'clear_cache_disabled',
            true
        );

        /** @var SwiftypeSiteTree $page */
        $page = $this->objFromFixture(SwiftypeSiteTree::class, 'page2');

        // Make sure our page is published before we begin
        $page->publishSingle();
        $key = str_replace('\\', '', $page->ClassName . $page->ID);

        // Make sure we don't have any Cache set from the above publishing
        $page->clearCacheAll();

        $page->doUnpublish();

        $expectedUrls = [
            'localhost/page2/',
        ];
        $urls = [];

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $page->getUrlsToCrawl();

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

        $this->assertEquals($expectedUrls, $urls, '', 0.0, 10, true);
    }

    /**
     * @throws Exception
     */
    public function testUrlsToCrawlSegmentChanged(): void
    {
        // Set our config to not clear caches after un/publish, so that we can easily fetch the Urls for our test
        Config::inst()->update(
            SwiftypeSiteTreeCrawlerExtension::class,
            'clear_cache_disabled',
            true
        );

        /** @var SwiftypeSiteTree $page */
        $page = $this->objFromFixture(SwiftypeSiteTree::class, 'page3');

        // Make sure our page is published before we begin
        $page->publishSingle();
        $key = str_replace('\\', '', $page->ClassName . $page->ID);

        // Make sure our cache is flushed from the above publishing
        $page->clearCacheAll();

        // Update our URL Segment
        $page->URLSegment = 'page3Changed';
        // Publish single so that Urls to crawl is populated
        $page->publishSingle();

        // We expect two Urls now. One from before the segment change, and one from after it
        $expectedUrls = [
            'localhost/page3/',
            'localhost/page3changed/',
        ];
        $urls = [];

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $page->getUrlsToCrawl();

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

        $this->assertEquals($expectedUrls, $urls, '', 0.0, 10, true);
    }

    public function testUrlsToCrawlCacheCleared(): void
    {
        /** @var SwiftypeSiteTree $page */
        $page = $this->objFromFixture(SwiftypeSiteTree::class, 'page1');

        // Publish single so that Urls to crawl is populated
        $page->publishSingle();
        $key = str_replace('\\', '', $page->ClassName . $page->ID);

        // Grab the Urls that we expect to have been collated
        $urlsToCrawl = $page->getUrlsToCrawl();

        // Check that the key exists for our page
        $this->assertArrayNotHasKey($key, $urlsToCrawl);
    }
}
