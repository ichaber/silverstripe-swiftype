<?php

namespace Ichaber\SSSwiftype\Extensions;

use Ichaber\SSSwiftype\Service\SwiftypeCrawler;
use Ichaber\SSSwiftype\Tests\Fake\SwiftypeFile;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Versioned\Versioned;

/**
 * Class SwiftypeFileCrawlerExtension
 *
 * @package Ichaber\SSSwiftype\Extensions
 * @property SwiftypeFile|$this $owner
 */
class SwiftypeFileCrawlerExtension extends DataExtension
{
    /**
     * Urls to crawl
     *
     * array keyed by getOwnerKey
     *
     * @var array
     */
    private $urlsToCrawl = [];

    /**
     * @param array $urls
     */
    public function setUrlsToCrawl(array $urls) {
        $this->urlsToCrawl = $urls;
    }

    /**
     * @return array
     */
    public function getUrlsToCrawl(): array
    {
        return $this->urlsToCrawl;
    }

    /**
     * We need to collate Urls before we write, just in case an author has changed the File's name (URL). If they
     * have, then we need to request Swiftype to reindex both the old Url (which should then be marked by Swiftype
     * as a 404), and the new Url
     */
    public function onBeforeWrite(): void
    {
        $this->collateUrls();
    }

    /**
     * After a publish has occurred, we can collate and process immediately (no need to split things out like during
     * an unpublish)
     *
     * @return void
     */
    public function onAfterPublish(): void
    {
        $this->collateUrls();
        $this->processCollatedUrls();

        // Check to see if the clearing of cache has been disabled (useful for unit testing, or any other reason you
        // might have to disable it)
        $clearCacheDisabled = Config::inst()->get(static::class, 'clear_cache_disabled');

        if ($clearCacheDisabled) {
            return;
        }

        // It's important that we clear the cache after we have finished requesting reindex from Swiftype
        $this->clearCacheSingle();
    }

    /**
     * We need to collate the Urls to be purged *before* we complete the unpublish action (otherwise, the LIVE Urls
     * will no longer be available, since the page is now unpublished)
     */
    public function onBeforeUnpublish(): void
    {
        $this->collateUrls();
    }

    /**
     * After the unpublish has completed, we can now request Swiftype to reindex the Urls that we collated
     */
    public function onAfterUnpublish(): void
    {
        $this->processCollatedUrls();

        // Check to see if the clearing of cache has been disabled (useful for unit testing, or any other reason you
        // might have to disable it)
        $clearCacheDisabled = Config::inst()->get(static::class, 'clear_cache_disabled');

        if ($clearCacheDisabled) {
            return;
        }

        // It's important that we clear the cache after we have finished requesting reindex from Swiftype
        $this->clearCacheSingle();
    }

    /**
     * You may need to clear the cache at some point during your particular process
     *
     * Reset all Urls for any/all objects that might be in the cache (keeping in mind that Extensions are singleton,
     * so the UrlsToCache could be accessed via singleton and it could contain Urls for many owner objects)
     *
     * We don't use flushCache (which is called from DataObject) because this is called between write and un/publish,
     * and we need our cache to persist through these states
     */
    public function clearCacheAll(): void
    {
        $this->setUrlsToCrawl([]);
    }

    /**
     * You may need to clear the cache at some point during your particular process
     *
     * Reset only the Urls related to this particular owner object (keeping in mind that Extensions are singleton,
     * so the UrlsToCache could be accessed via singleton and it could contain Urls for many owner objects)
     *
     * We don't use flushCache (which is called from DataObject) because this is called between write and un/publish,
     * and we need our cache to persist through these states
     */
    public function clearCacheSingle(): void
    {
        $urls = $this->getUrlsToCrawl();
        $key = $this->getOwnerKey();

        // Nothing for us to do here
        if ($key === null) {
            return;
        }

        // Nothing for us to do here
        if (!array_key_exists($key, $urls)) {
            return;
        }

        // Remove this key and it's Urls
        unset($urls[$key]);

        $this->setUrlsToCrawl($urls);
    }

    /**
     * Collate Urls to crawl
     *
     * Extensions are singleton, so we use the owner key to make sure that we're only processing Urls directly related
     * to the desired record.
     *
     * You might need to collate more than one URL per Page (maybe you're using Fluent or another translation module).
     * This is the method you will want to override in order to add that additional logic.
     */
    public function collateUrls(): void
    {
        if (!$this->checkFileIsToBeReindexed()) {
            return;
        }

        // Grab any existing Urls so that we can add to it
        $urls = $this->getUrlsToCrawl();

        // Set us to a LIVE stage/reading_mode
        $this->withVersionContext(function() use (&$urls) {
            /** @var File $owner */
            $owner = $this->getOwner();
            $key = $this->getOwnerKey();

            // We can't do anything if we don't have a key to use
            if ($key === null) {
                return;
            }

            // Create a new container for this key
            if (!array_key_exists($key, $urls)) {
                $urls[$key] = [];
            }

            // Grab the absolute live link without ?stage=Live appended
            $link = $owner->getAbsoluteURL();

            // If this record is not published, or we're unable to get a "Live Link" (for whatever reason), then there
            // is nothing more we can do here
            if (!$link) {
                return;
            }

            // Nothing for us to do here, the Link is already being tracked
            if (in_array($link, $urls[$key])) {
                return;
            }

            // Add our base URL to this key
            $urls[$key][] = $link;
        });

        // Update the Urls we have stored for indexing
        $this->setUrlsToCrawl($urls);
    }

    /**
     * Send requests to Swiftype to reindex each of the Urls that we have previously collated
     */
    protected function processCollatedUrls(): void
    {
        // Fetch the Urls that we need to reindex
        $key = $this->getOwnerKey();

        // We can't do anything if we don't have a key to process
        if ($key === null) {
            return;
        }

        $urls = $this->getUrlsToCrawl();

        // There is nothing for us to do here if there are no Urls
        if (count(array_keys($urls)) === 0) {
            return;
        }

        // There are no Urls for this particular key
        if (!array_key_exists($key, $urls)) {
            return;
        }

        // Force the reindexing of each URL we collated
        foreach ($urls[$key] as $url)  {
            $this->forceSwiftypeIndex($url);
        }
    }

    /**
     * @param string $updateUrl
     * @return bool
     */
    protected function forceSwiftypeIndex(string $updateUrl): bool
    {
        // We don't reindex dev environments
        if (Director::isDev()) {
            return true;
        }

        $crawler = SwiftypeCrawler::create();

        return $crawler->send($updateUrl);
    }

    /**
     * @return string
     */
    protected function getOwnerKey(): ?string
    {
        $owner = $this->owner;

        // Can't generate a key if the owner has not yet been written to the DB
        if (!$owner->isInDB()) {
            return null;
        }

        $key = str_replace('\\', '', $owner->ClassName . $owner->ID);

        return $key;
    }

    /**
     * Method to check our file types whitelist since we don't want to index files that aren't required in the index
     * e.g. image files.
     *
     * @return bool
     */
    protected function checkFileIsToBeReindexed()
    {
        // only reindex file types we need.
        $fileType = File::get_file_extension($this->getOwner()->Filename);

        return in_array($fileType, $this->getOwner()->config()->get('reindex_files_whitelist'),true);
    }

    /**
     * Sets the version context to Live as that's what crawlers will (normally) see
     *
     * The main function is to suppress the ?stage=Live querystring. LeftAndMain will set the default
     * reading mode to 'DRAFT' when initialising so to counter this we need to re-set the default
     * reading mode back to LIVE
     *
     * @param callable $callback
     */
    private function withVersionContext(callable $callback): void
    {
        Versioned::withVersionedMode(static function() use ($callback) {
            // Grab our current stage and reading mode
            $originalDefaultReadingMode = Versioned::get_default_reading_mode();
            $originalReadingMode = Versioned::get_reading_mode();
            $originalStage = Versioned::get_stage();

            // Set our stage and reading mode to LIVE
            Versioned::set_default_reading_mode('Stage.' . Versioned::LIVE);
            Versioned::set_reading_mode('Stage.' . Versioned::LIVE);
            Versioned::set_stage(Versioned::LIVE);

            // Process whatever callback was provided
            $callback();

            // Set us back to the original stage and reading mode
            if ($originalReadingMode) {
                Versioned::set_default_reading_mode($originalDefaultReadingMode);
                Versioned::set_reading_mode($originalReadingMode);
            }

            if ($originalStage) {
                Versioned::set_stage($originalStage);
            }
        });
    }
}
