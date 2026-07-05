<?php
use PHPUnit\Framework\TestCase;

/** Verifies the four form-subsystem fixes on congruency `main`. */
#[Category('forms', 'Form subsystem fixes (split→explode, real getters, missing constants)')]
final class FormsFixTest extends TestCase
{
    public function testRadioParserUsesExplodeNotRemovedSplit(): void
    {
        $r = new RadioSelect();
        $r->setElementString('<<Vanilla>><<Chocolate>><<Strawberry>>');
        $this->assertSame(['Vanilla', 'Chocolate', 'Strawberry'], $r->getOptions());
    }

    public function testValidateElementStringAcceptsValidString(): void
    {
        $this->assertTrue(FormElementUtils::validateElementString('<<a>><<b>>'));
    }

    public function testIsRequiredAndGetRequiredAreRealGetters(): void
    {
        $el = new TextField();
        $el->setRequired(true);
        $this->assertTrue($el->isRequired());
        $this->assertTrue($el->getRequired());
    }

    public function testFormDbConstantsDefinedAndDaoConstructs(): void
    {
        $this->assertTrue(defined('MYSQL_FORM_DATABASE'));
        $this->assertInstanceOf(FormElementDAO::class, new FormElementDAO());
    }
}
