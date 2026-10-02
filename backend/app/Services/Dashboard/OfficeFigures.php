<?php

namespace App\Services\Dashboard;

/** Aggregated figures of one office within the current scope. */
final readonly class OfficeFigures
{
    public function __construct(
        public string $slug,
        public string $name,
        public int $total,
        public int $male,
        public int $female,
        public bool $selected = false,
    ) {}

    public function total(): int
    {
        return $this->total;
    }

    public function withSelected(bool $selected): self
    {
        return new self($this->slug, $this->name, $this->total, $this->male, $this->female, $selected);
    }
}
