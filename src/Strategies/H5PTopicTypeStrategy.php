<?php

namespace EscolaLms\CoursesImportExport\Strategies;

use EscolaLms\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use EscolaLms\HeadlessH5P\Services\Contracts\HeadlessH5PServiceContract;
use EscolaLms\HeadlessH5P\Services\HeadlessH5PService;
use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Http\UploadedFile;

class H5PTopicTypeStrategy implements TopicImportStrategy
{
    private HeadlessH5PServiceContract $h5PService;

    public function __construct(HeadlessH5PServiceContract $h5PService)
    {
        $this->h5PService = $h5PService;
    }

    function make(string $path, array $data): ?int
    {
        // TODO implement
        // return $this->h5PService->uploadFile($contentId, $field, $token, $nonce);
        return null;
    }
}
