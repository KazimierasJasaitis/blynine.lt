<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/experiences.php';

$items = \Blynine\experiences();
if (count($items) !== 3) {
    throw new RuntimeException('The initial release must have three experiences.');
}
foreach ($items as $index => $expected) {
    $actual = \Blynine\choose_experience(function (int $min, int $max) use ($index, $items): int {
        if ($min !== 0 || $max !== count($items) - 1) {
            throw new RuntimeException('The draw must cover every registered experience equally.');
        }
        return $index;
    });
    if ($actual !== $expected) {
        throw new RuntimeException('Wrong experience selected.');
    }
}
try {
    \Blynine\choose_experience(fn () => 99);
    throw new RuntimeException('Out-of-range draws must be rejected.');
} catch (UnexpectedValueException) {
}
echo "PASS: equal selection boundaries, all registered outcomes, invalid draw.\n";
