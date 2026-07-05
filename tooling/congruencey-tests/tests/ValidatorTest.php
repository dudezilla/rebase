<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Exhaustive branch exercise of ValidateFields — the input whitelist.
 * Each case drives a specific decision edge (match+equal / match+unequal /
 * no-match). Measured 100% branch coverage is verified separately by
 * coverage/branch_test.php; this pins the behaviour.
 */
final class ValidatorTest extends TestCase
{
    public static function pageKey(): array
    {
        return [
            'match & equal'        => ['catalog', 'catalog'],
            'match but trailing'   => ['ab1', null],
            'no letters (no match)'=> ['1', null],
        ];
    }

    #[DataProvider('pageKey')]
    public function testValidatePageKey(string $in, ?string $exp): void
    {
        $this->assertSame($exp, @ValidateFields::validatePageKey($in));
    }

    public static function numericKey(): array
    {
        return [['123', '123'], ['12x', null], ['abc', null]];
    }

    #[DataProvider('numericKey')]
    public function testValidateNumericKey(string $in, ?string $exp): void
    {
        $this->assertSame($exp, @ValidateFields::validateNumericKey($in));
    }

    public static function formKey(): array
    {
        return [['a-b_1', 'a-b_1'], ['a b', null]];
    }

    #[DataProvider('formKey')]
    public function testValidateFormKey(string $in, ?string $exp): void
    {
        $this->assertSame($exp, @ValidateFields::validateFormKey($in));
    }

    public function testValidateItemKeyDelegatesToNumeric(): void
    {
        $this->assertSame('42', @ValidateFields::validateItemKey('42'));
    }
}
