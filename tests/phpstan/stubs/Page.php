<?php

// phpcs:disable PSR1.Classes.ClassDeclaration
// PSR-1 wants one class per file and a vendor-level namespace. Neither is possible for a
// stub of the project globals: they have to be declared exactly the way a site's
// app/code does it (global namespace, both classes here) for phpstan to resolve the same
// names a real project would provide.

/**
 * PHPStan stubs for the two global classes a SilverStripe *project* (not this module)
 * provides.
 *
 * src/ references the global Page and PageController classes, which only exist in a
 * site's app/code. A module-only checkout - such as the one the CI phplinting job
 * analyses - has no app/ directory at all, so phpstan used to be pointed at app/code
 * via scanDirectories and aborted before analysing anything ("Scanned directory
 * .../app/code does not exist").
 *
 * These stubs are discovered through phpstan's scanFiles instead, so analysing the
 * module no longer depends on a project skeleton being present on disk. They are never
 * loaded by Composer's autoloader (no namespace, and psr-4 only resolves the
 * Dynamic\ElementalTemplates\Tests\ namespace from tests/), and /tests is
 * export-ignored, so sites installing this module never receive them.
 *
 * tests/phpstan/_manifest_exclude keeps these files out of SilverStripe's own class
 * manifest: without it a site that has its own app/code/Page.php would fatal with
 * 'There are two files containing the Page class' as soon as its test suite boots a
 * kernel with test classes included.
 */

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;

class Page extends SiteTree
{
}

class PageController extends ContentController
{
}
