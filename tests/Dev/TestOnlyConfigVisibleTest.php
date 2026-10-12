<?php

namespace Dynamic\ElementalTemplates\Tests\Dev;

use Dynamic\ElementalTemplates\Tests\TestOnly\AltAreaSamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\NoAreaTemplate;
use Dynamic\ElementalTemplates\Tests\TestOnly\OnlyAltArea;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Guards the condition that turned dynamic/silverstripe-elemental-templates#146 red.
 *
 * The `private static` config of a TestOnly class is only visible when the class manifest the config
 * cache was built from included tests. When it did not, every declared value silently falls back to
 * its default, and the one that matters is DataObject::$table_name: the fully-qualified-name fallback
 * plus Versioned's '_Versions' suffix is 68 characters for AltAreaSamplePage, over MySQL's 64
 * character table-name limit, so the temp database cannot be built and every test in the class errors
 * before its first assertion.
 *
 * This test asserts the declared config is visible to the running kernel, so a test run that reuses a
 * tests-excluded config cache fails here, loudly, instead of failing somewhere else for a confusing
 * reason. It declares `$usesDatabase = false`, so no temp database or fixtures are built; note that it
 * still needs a reachable database connection, because `DataObjectSchema::tableName()` passes the name
 * through `Convert::raw2sql()`, which escapes via the active DB connector.
 */
class TestOnlyConfigVisibleTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = false;

    /**
     * MySQL refuses table names longer than 64 characters, and Versioned appends '_Versions' (9
     * characters) to the declared name.
     */
    private const MAX_DECLARED_TABLE_NAME = 55;

    /**
     * Every TestOnly DataObject must be reachable by the table name it declares, not by the
     * FQN-derived fallback.
     */
    public function testDeclaredTableNamesAreVisible(): void
    {
        $schema = DataObjectSchema::singleton();

        $this->assertSame('AltAreaSamplePage', $schema->tableName(AltAreaSamplePage::class));
        $this->assertSame('OnlyAltArea', $schema->tableName(OnlyAltArea::class));
        $this->assertSame('NoAreaTemplate', $schema->tableName(NoAreaTemplate::class));
        $this->assertSame('SamplePage', $schema->tableName(SamplePage::class));
    }

    /**
     * The declared name is the one that has to survive Versioned's suffix, so pin the length budget too
     * rather than only the exact strings above.
     */
    public function testDeclaredTableNamesLeaveRoomForTheVersionsSuffix(): void
    {
        $schema = DataObjectSchema::singleton();

        foreach ([AltAreaSamplePage::class, OnlyAltArea::class, NoAreaTemplate::class, SamplePage::class] as $class) {
            $this->assertLessThanOrEqual(
                self::MAX_DECLARED_TABLE_NAME,
                strlen($schema->tableName($class) ?? ''),
                sprintf('%s declares a table name that leaves no room for the "_Versions" suffix.', $class)
            );
        }
    }

    /**
     * Relations a fixture declares in its own private statics are the other thing a tests-excluded
     * config cache drops. AltAreaSamplePage is the fixture whose whole purpose is a second elemental
     * area, so its absence is what breaks the applicator tests.
     */
    public function testFixtureRelationsAreVisible(): void
    {
        $hasOne = Config::inst()->get(AltAreaSamplePage::class, 'has_one', Config::UNINHERITED);
        $this->assertIsArray($hasOne);
        $this->assertArrayHasKey('ElementalHomePage', $hasOne ?? []);
        $this->assertSame(ElementalArea::class, $hasOne['ElementalHomePage'] ?? null);

        $owns = Config::inst()->get(AltAreaSamplePage::class, 'owns', Config::UNINHERITED);
        $this->assertContains('ElementalHomePage', $owns ?? []);

        $onlyAltAreaHasOne = Config::inst()->get(OnlyAltArea::class, 'has_one', Config::UNINHERITED);
        $this->assertSame(ElementalArea::class, $onlyAltAreaHasOne['ElementalHomePage'] ?? null);
    }

    /**
     * Control case for the assertions above: a vendor DataObject that declares its own table_name lives
     * in every class manifest, tests included or not, so its declared value is always visible. Reading
     * it back as the short declared name shows the schema reader really does return declared config -
     * the fixtures above failing on their FQN fallback is a difference it can see, not a name that is
     * always mangled.
     */
    public function testAVendorDataObjectTableNameIsAlsoReadFromItsOwnDeclaration(): void
    {
        $tableName = DataObjectSchema::singleton()->tableName(ElementalArea::class);

        $this->assertSame('ElementalArea', $tableName);
        $this->assertStringNotContainsString('DNADesign_Elemental', $tableName ?? '');
    }
}
