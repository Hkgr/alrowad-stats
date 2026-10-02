<?php

namespace App\Services\Dashboard;

/**
 * Registered benefits of one slice (sector, project, activity, period...).
 * Total is the sum of record totals; male/female are sums of the reported values only, so
 * total − male − female is the part whose gender is not reported in the source.
 */
final readonly class Figures
{
    public function __construct(
        public int $total,
        public int $male,
        public int $female,
        /** Distinct projects with records in the slice. */
        public int $projects,
        /** Distinct offices with records in the slice. */
        public int $offices,
        public int $records,
        public ?int $disabled = null,
    ) {}

    public function genderUnreported(): int
    {
        return max(0, $this->total - $this->male - $this->female);
    }

    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->total, (int) $row->male, (int) $row->female, (int) $row->projects, (int) $row->offices,
            (int) $row->records, $row->disabled === null ? null : (int) $row->disabled,
        );
    }
}
