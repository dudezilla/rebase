<?php
use PHPUnit\Framework\TestCase;

/**
 * Pins the adversarial baseline: the most recent attack-loop iteration must
 * reproduce the known verdicts (3 confirmed vulns + 1 defended control).
 * Populated by pwdriver/attack.js; the `verify` runner executes it first.
 */
final class AttackBaselineTest extends TestCase
{
    private const DB = '/home/notificationsforsteven/congruency-harness/telemetry.sqlite';
    private const EXPECTED = [
        'contact-xss'                => 'CONFIRMED',
        'ssti-tag-injection'         => 'CONFIRMED',
        'price-tampering'            => 'CONFIRMED',
        'pagekey-injection (control)'=> 'SAFE',
    ];

    public function testLatestAttackVerdictsMatchBaseline(): void
    {
        if (!is_file(self::DB)) {
            $this->markTestSkipped('no telemetry db — run the attack loop first');
        }
        $pdo = new PDO('sqlite:' . self::DB);
        $iter = $pdo->query('SELECT MAX(iteration) FROM attacks')->fetchColumn();
        if (!$iter) {
            $this->markTestSkipped('no attacks recorded yet');
        }
        foreach (self::EXPECTED as $name => $expected) {
            $st = $pdo->prepare('SELECT verdict FROM attacks WHERE name=? AND iteration=? ORDER BY id DESC LIMIT 1');
            $st->execute([$name, $iter]);
            $this->assertSame($expected, $st->fetchColumn(), "attack vector: $name");
        }
    }
}
