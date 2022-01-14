<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\CoursesImportExport\Enums\CoursesImportExportEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseScormScoExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray($request): array
    {
        $scormSco = $this->scormSco;
        return [
            'id' => $scormSco->getKey(),
            'uuid' => $scormSco->uuid,
            'identifier' => $scormSco->identifier,
            'scorm_file' => CoursesImportExportEnum::SCORM_FILE,
        ];
    }
}
