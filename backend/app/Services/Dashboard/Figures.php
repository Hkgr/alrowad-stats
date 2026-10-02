<?php

namespace App\Services\Dashboard;

/** Registered benefits of one slice (sector, project, period...). Total is always male + female. */
final readonly class Figures
{
    public function __construct(
        public int $male,
        public int $female,
        /** Distinct projects with records in the slice. */
        public int $projects,
        /** Distinct offices with records in the slice. */
        public int $offices,
    ) {}

    public function total(): int
    {
        return $this->male + $this->female;
    }

    public static function fromRow(object $row): self
    {
        return new self((int) $row->male, (int) $row->female, (int) $row->projects, (int) $row->offices);
    }
}
