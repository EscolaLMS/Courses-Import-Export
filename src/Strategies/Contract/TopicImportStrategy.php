<?php

namespace EscolaLms\CoursesImportExport\Strategies\Contract;

interface TopicImportStrategy
{
    function make(string $path, array $data): ?int;
}
