<?php

namespace Ichaber\SSSwiftype\Extensions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

/**
 * Class SwiftypeSiteTreeCrawlerExtension
 *
 * @package Ichaber\SSSwiftype\Extensions
 * @property SiteTree|$this $owner
 */
class SwiftypeSiteTreeCrawlerExtension extends SiteTreeExtension
{
    /**
     * @param SiteTree|mixed $original
     */
    public function onAfterPublish(&$original): void
    {
        parent::onAfterPublish($original);

        $this->withVersionContext(function() {
            $this->forceSwiftypeIndex();
        });
    }

    public function onAfterUnpublish(): void
    {
        parent::onAfterUnpublish();

        $this->withVersionContext(function() {
            $this->forceSwiftypeIndex();
        });
    }

    /**
     * According to the documentation of the RestfulService only xml output is supported at this stage.
     * As we need json, we opted to just use standard PHP curl processing.
     *
     * For future proofing purposes, this function returns a boolean value.
     *
     * Note: This request to Swiftype is ignored any time a request is sent for an existing URL (unfortunately). For
     * reindexing edited Pages, we must unfortunately rely on Constant Crawl. This is probably a design choice on
     * Swiftype's part.
     *
     * @return bool
     */
    protected function forceSwiftypeIndex(): bool
    {
        // We don't reindex dev environments.
        if (Director::isDev()) {
            return true;
        }

        /** @var SiteConfig $config */
        $config = SiteConfig::current_site_config();

        // Are you not using SwiftypeSiteConfigFieldsExtension? That's cool, just be sure to implement these as fields
        // or methods in some other manor so that they are available via relField.

        // You might want to implement this via Environment variables or something. Just make sure SiteConfig has access
        // to that variable, and return it here.
        $swiftypeEnabled = (bool) $config->relField('SwiftypeEnabled');

        if (!$swiftypeEnabled) {
            return true;
        }

        $logger = $this->getLogger();

        // If you have multiple Engines per site (maybe you use Fluent with a different Engine on each Locale), then
        // this provides some basic ability to have different credentials returned based on the application state.
        $engineSlug = $config->relField('SwiftypeEngineSlug');
        $domainID = $config->relField('SwiftypeDomainID');
        $apiKey = $config->relField('SwiftypeAPIKey');

        if (!$engineSlug) {
            $trace = debug_backtrace();
            $logger->warning(
                'Swiftype Engine Slug value has not been set. Settings > Swiftype Search > Swiftype Engine Slug',
                array_shift($trace) // Add context (for RaygunHandler) by using the last item on the stack trace.
            );

            return false;
        }

        if (!$domainID) {
            $trace = debug_backtrace();
            $logger->warning(
                'Swiftype Domain ID has not been set. Settings > Swiftype Search > Swiftype Domain ID',
                array_shift($trace) // Add context (for RaygunHandler) by using the last item on the stack trace.
            );

            return false;
        }

        if (!$apiKey) {
            $trace = debug_backtrace();
            $logger->warning(
                'Swiftype API Key has not been set. Settings > Swiftype Search > Swiftype Production API Key',
                array_shift($trace) // Add context (for RaygunHandler) by using the last item on the stack trace.
            );

            return false;
        }

        $updateUrl = $this->getOwner()->getAbsoluteLiveLink(false);

        // Create curl resource.
        $ch = curl_init();

        // Set url.
        curl_setopt(
            $ch,
            CURLOPT_URL,
            sprintf(
                'https://api.swiftype.com/api/v1/engines/%s/domains/%s/crawl_url.json',
                $engineSlug,
                $domainID
            )
        );

        // Set request method to "PUT".
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        // Set headers.
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        // Return the transfer as a string.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set our PUT values.
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'auth_token' => $apiKey,
            'url' => $updateUrl,
        ]));

        // $output contains the output string.
        $output = curl_exec($ch);

        // Close curl resource to free up system resources.
        curl_close($ch);

        if (!$output) {
            $trace = debug_backtrace();
            $logger->warning(
                'We got no response from Swiftype for reindexing page: ' . $updateUrl,
                array_shift($trace) // Add context (for RaygunHandler) by using the last item on the stack trace.
            );

            return false;
        }

        $jsonOutput = json_decode($output, true);
        if (!empty($jsonOutput) && array_key_exists('error', $jsonOutput)) {
            $message = $jsonOutput['error'];
            $context = ['exception' => new Exception($message)];

            // Add context (for RaygunHandler) by using the last item on the stack trace.
            $logger->warning($message, $context);

            return false;
        }

        return false;
    }

    /**
     * @return LoggerInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Sets the version context to Live as that's what crawlers will (normally) see
     * the main function is to suppress teh ?stage=Live querystring
     *
     * @param callable $callback
     * @return void
     */
    private function withVersionContext(callable $callback) {
        Versioned::withVersionedMode(function() use ($callback) {
            $originalReadingMode = Versioned::get_default_reading_mode();
            Versioned::set_stage(Versioned::LIVE);
            Versioned::set_default_reading_mode(Versioned::get_reading_mode());
            $callback();
            Versioned::set_default_reading_mode($originalReadingMode);
        });
    }
}
