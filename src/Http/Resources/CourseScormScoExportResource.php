<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

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
        return [
            'id' => $this->getKey(),
            'uuid' => $this->uuid,
            'identifier' => $this->identifier,
            'entry_url' => $this->entry_url,
            'title' => $this->title,
        ];
    }
}
