<?php

declare(strict_types=1);

namespace Dynamic\ElementalTemplates\Tests\Dev;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Cache\CacheFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Cache factory that keeps the manifest and config caches in memory, for the PHPUnit process only.
 *
 * SilverStripe's cached config collection has a single cache key that does not record whether the class
 * manifest it was built from included TestOnly classes (silverstripe/config CachedConfigCollection reads
 * its cache entry unconditionally). So a config cache written by `sake dev/build`, whose class manifest
 * excludes tests, is read back as valid by the next PHPUnit process - and every `private static` declared
 * on a TestOnly class is missing from the config it gets. See
 * dynamic/silverstripe-elemental-templates#146.
 *
 * Pointing SS_MANIFESTCACHE at this factory (see phpunit.xml.dist) gives the test run its own caches that
 * are built from a test-inclusive class manifest, and writes nothing to the shared on-disk cache the rest
 * of the site uses.
 */
class InMemoryManifestCacheFactory implements CacheFactory
{
    /**
     * @param array<string, mixed> $params The usual cache params (namespace, defaultLifetime, directory),
     *                                     all ignored: nothing is written to disk.
     */
    public function create(string $service, array $params = []): CacheInterface
    {
        // A fresh adapter per service, so different cache namespaces never share an array.
        return new Psr16Cache(new ArrayAdapter());
    }
}
