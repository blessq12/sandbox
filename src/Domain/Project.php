<?php

namespace Andrewmaster\Sandbox\Domain;

class Project
{
    public function __construct(
        public string $name,
        public string $title,
        public string $path,
        public ?string $entry = null,
        public ?string $testsScenariosDir = null,
        public ?string $testsRoutesDir = null,
        public array $env = []
    ) {}
}
