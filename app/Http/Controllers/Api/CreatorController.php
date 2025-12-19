<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatorResource;
use App\Http\Resources\VideoResource;
use App\Models\Creator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    /**
     * Get creator profile
     */
    public function show(string $id): JsonResponse
    {
        $creator = Creator::active()
            ->withCount(['videos' => function ($query) {
                $query->published();
            }])
            ->find($id);

        if (!$creator) {
            return $this->notFound('Creator not found');
        }

        return $this->success(new CreatorResource($creator));
    }

    /**
     * Get creator's videos
     */
    public function videos(Request $request, string $id): JsonResponse
    {
        $creator = Creator::active()->find($id);

        if (!$creator) {
            return $this->notFound('Creator not found');
        }

        $perPage = $request->input('per_page', 20);

        $videos = $creator->videos()
            ->published()
            ->with(['category', 'creator'])
            ->recent()
            ->paginate($perPage);

        return $this->paginated(
            $videos->setCollection(
                VideoResource::collection($videos->getCollection())
            )
        );
    }
}

