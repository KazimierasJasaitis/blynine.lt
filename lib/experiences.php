<?php
declare(strict_types=1);

namespace Blynine;

/** Add an experience here to give it the same chance as every other entry. */
function experiences(): array
{
    return [
        ['id' => 'pirmas-blynas', 'type' => 'redirect', 'url' => 'https://pirmasblynas.lt/'],
        ['id' => 'dictionary', 'type' => 'dictionary'],
        ['id' => 'game', 'type' => 'game'],
    ];
}

/** The optional random source makes the selection contract testable without chance. */
function choose_experience(?callable $draw = null): array
{
    $items = experiences();
    $draw ??= 'random_int';
    $index = $draw(0, count($items) - 1);
    if (!is_int($index) || !isset($items[$index])) {
        throw new \UnexpectedValueException('Invalid experience selection.');
    }
    return $items[$index];
}
