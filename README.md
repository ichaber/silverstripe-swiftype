# SilverStripe Swiftype

[![Build Status](http://img.shields.io/travis/ichaber/silverstripe-swiftype.svg?style=flat)](https://travis-ci.org/ichaber/silverstripe-swiftype)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ichaber/silverstripe-swiftype/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ichaber/silverstripe-swiftype/?branch=master)
[![codecov](https://codecov.io/gh/ichaber/silverstripe-swiftype/branch/master/graph/badge.svg)](https://codecov.io/gh/ichaber/silverstripe-swiftype)

# *WARNING*

This module is still under development, trying to standardize existing custom code and is currently tailored towards
using the Swiftype crawler for indexing.

The ultimate goal is to have a drop in module for every silverstripe-swiftype project and eventually feed it back into
the silverstripe space.

## Installation

Install with Composer:

```
composer require ichaber/silverstripe-swiftype
```

Also see [Configuration](#Configuration). Extensions and configurations are not applied automatically.

## Documentation

Provides the necessary code for configuring the swiftype integration code with the access key and for adding metadata
tags to the site code so the Swiftype crawler can be index them.

The code also provides an extension which triggers a re-index after a page is published/unpublished in the SilverStripe
CMS admin area.

## How does it work?

### MetaTags

There are a bunch of standard `SwiftypeMetaTag` classes. Each of these classes represents one of the standard
SilverStripe SiteTree fields *or methods*, and will be used to output a single meta tag into your markup.

Here are the standard classes, and which SiteTree field/method they represent:

- SwiftypeMetaTagDescription (`MetaDescription` field)
- SwiftypeMetaTagPublishedAt (`LastEdited` field)
- SwiftypeMetaTagTitle (`Title` field)
- SwiftypeMetaTagURL (`Link()` method)

Additionally, there is a robots class, which can be used to output `noindex` and/or `nofollow` (configurable) when
your SiteTree record has `ShowInSearch` set to `0`. By default, this will render with just `noidex`, but you can
update it's config to also render with `nofollow`.

- SwiftypeMetaTagRobots

### Templating

If you are using out of the box functionality (see [Configuration](#Configuration)), then in your template, you can simply
use `$SwiftypeMetaTags` to output all of the meta tags that you have set up as part of your install.

### Crawling

If you are using out of the box functionality (see [Configuration](#Configuration)), then when you publish a page, a
request will be sent to Swiftype for it to crawl that page.

## Configuration

### Simple

If you just want to plug and play, then apply the following three extensions.

```yml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeSiteConfigFieldsExtension
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeSiteTreeCrawlerExtension
    - Ichaber\SSSwiftype\Extensions\SwiftypeMetaTagContentExtension
```

These will provide you with:
- The standard CMS fields for adding your Swiftype credentials.
- A template variable (`$SwiftypeMetaTags`) for outputting your meta tags.
- Re-index requests to Swiftype on SiteTree publishing.

You will then need specify which Meta Tags you would like to use. You can do this in two ways.

In a config yaml:
```yml
App\Page\MyPage:
  swiftype_meta_tag_classes:
    - Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagDescription
    - Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagPublishedAt
    - Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagRobots
    - Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagTitle
    - Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagURL
```

Or in your model:
```php
class MyPage extends SiteTree
{
    /**
     * @var array
     */
    private static $swiftype_meta_tag_classes = [
        SwiftypeMetaTagDescription::class,
        SwiftypeMetaTagPublishedAt::class,
        SwiftypeMetaTagRobots::class,
        SwiftypeMetaTagTitle::class,
        SwiftypeMetaTagURL::class,
    ];
}
```

Either of these methods should provide you with good control if you have different page types needing different meta
tags.

**A quick note on the Robots class**

Swiftype uses the standard `<meta name="robots" content="noindex">` meta tag, so if you are already outputting this tag
through some other means, then you will want to exclude it here. Also see
[Customising the robots Meta Tag](#Customising the robots Meta Tag)

### Piece by piece

To use the standard SiteConfig CMS fields, you can apply `SwiftypeSiteConfigFieldsExtension` to your `SiteConfig`. This
will provide you with some basic options to set up a single engine for your site.

Currently there is minimal support for multiple engines on a single site - you will (most likely) need to add your own
implementation if you desire this.

```yml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeSiteConfigFieldsExtension
```

If you are using the Swiftype Crawler, and would like to add "re-crawl" actions after your pages un/publish, you can
apply `SwiftypeSiteTreeCrawlerExtension` to `SiteTree` (or another model of your choice).

```yml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeSiteTreeCrawlerExtension
```

If you would like SiteTree to have access to the standard template variable, then apply the following extension.

```yml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeMetaTagContentExtension
```

### Indexing Files
If you are using the Swiftype Crawler, and would like to add "re-crawl" actions after your Files un/publish, you can
apply `SwiftypeFileCrawlerExtension` to `File` (or another model of your choice).

```yml
SilverStripe\CMS\Model\File:
  extensions:
    - Ichaber\SSSwiftype\Extensions\SwiftypeFileCrawlerExtension
```
There is also a config to whitelist certain file types from being indexed/reindexed.
 ```yml
Ichaber\SSSwiftype\Extensions\SwiftypeFileCrawlerExtension:
  reindex_files_whitelist:
    - pdf
 ```
> NB: by default this config does not set any whitelisting.

## Adding your own Meta Tags

You can easily add your own classes to your objects (see [Installation](#Installation)).

Any classes that you add are expected to implement `SwiftypeMetaTagInterface`, but that's about it.

You can also feel free to extend `SwiftypeMetaTag`, if you would like access to methods like `generateMetaTagsString()`.

## Customising the robots Meta Tag

There are two configs available for the robots Meta Tag. These allow you to control whether you add `noindex` and/or 
`nofollow`. By befault, `noindex` is added, but we allow robots to follow.

```
Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagRobots:
  no_index: true
  no_follow: false
```

You can override these by adding your own config. EG: Adding both `noidex` and `nofollow`.

```yml

---
Name: app_swiftype_tags
After: swiftype_tags
---
Ichaber\SSSwiftype\MetaTags\SwiftypeMetaTagRobots:
  no_index: true
  no_follow: true
```

## Requirements

 * PHP 7.1 or higher
 * SilverStripe Framework 4.x
 * SilverStripe CMS 4.x
