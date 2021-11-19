<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\Courses\Facades\Topic;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        $topicable = $this->topicable;

        if (Topic::getResourceClass($this->topicable_type, 'export')) {
            $resourceClass = Topic::getResourceClass($this->topicable_type, 'export');
            $resource = new $resourceClass($this->topicable);
            $topicable = $resource->toArray($request);
        }

        return [
            'title' => $this->title,
            'active' => $this->active,
            'preview' => $this->preview,
            'topicable_type' => $this->topicable_type,
            'topicable' => $topicable,
            'summary' => $this->summary,
            'introduction' => $this->introduction,
            'description' => $this->description,
            'resources' => TopicExportResourceResource::collection($this->resources),
            'order' => $this->order,
            'json' => $this->json,
            'can_skip' => $this->can_skip,
        ];
    }
}
