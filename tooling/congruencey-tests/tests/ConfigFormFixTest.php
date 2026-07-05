<?php
use PHPUnit\Framework\TestCase;

/** Verifies the three ConfigForm admin-builder fixes. */
final class ConfigFormFixTest extends TestCase
{
    public function testObtainPriceRejectsNonNumeric(): void
    {
        // '12x99' must NOT parse now that the decimal dot is escaped.
        $this->assertNull(ConfigForm::obtainPrice('## Price=12x99 ##'));
    }

    public function testObtainPriceAcceptsRealDecimal(): void
    {
        $this->assertSame('12.99', ConfigForm::obtainPrice('## Price=12.99 ##'));
    }

    public function testObtainDescriptionIsNonGreedy(): void
    {
        $d = (string) ConfigForm::obtainDescription('## Description=First ## ## Description=Second ##');
        $this->assertStringContainsString('First', $d);
        $this->assertStringNotContainsString('Second', $d);
    }

    public function testInitFormArrayReturnsNullWhenIveMissing(): void
    {
        $pdo = new PDO('sqlite:' . CONGRUENCY_SQLITE);
        $pdo->exec('DROP TABLE IF EXISTS forms');
        $pdo->exec('CREATE TABLE forms (`key` INTEGER, name TEXT, formName TEXT, elementString TEXT,
                    implements TEXT, selection TEXT, required INTEGER, `order` INTEGER)');
        $pdo->exec("INSERT INTO forms VALUES (1,'opt','ConfigForm-6','<<x>>','RadioSelect','p',0,1)");
        unset($pdo);

        $_GET['productID'] = '6';
        PersistentObjectManager::setData('FORM_MANAGER', new FormManager());
        $fce = new ConfigFormFCE();
        $m = new ReflectionMethod(ConfigFormFCE::class, 'initFormArray');
        $m->setAccessible(true);
        $this->assertNull($m->invoke($fce), 'a form missing its IVE must return null, not dereference null');
    }
}
