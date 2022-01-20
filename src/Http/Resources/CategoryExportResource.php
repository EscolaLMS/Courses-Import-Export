<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryExportResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'parent' => $this->parent_id ? CategoryExportResource::make($this->parent) : null,
            'icon' => $this->icon,
            'icon_class' => $this->icon_class,
        ];
    }
}
