<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\HomeTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeTopicController extends Controller
{
    /**
     * Get all active home topics for display on mobile app home screen
     */
    public function index(): JsonResponse
    {
        $homeTopics = HomeTopic::active()
            ->ordered()
            ->with(['category' => function ($query) {
                $query->select('id', 'name', 'icon', 'color');
            }])
            ->get()
            ->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'category_id' => $topic->category_id,
                    'title' => $topic->title,
                    'icon' => $topic->icon,
                    'image_url' => $topic->image_url,
                    'color' => $topic->color ?? $topic->category->color,
                    'sort_order' => $topic->sort_order,
                    'is_active' => $topic->is_active,
                ];
            });

        return $this->success($homeTopics);
    }

    /**
     * Get curated videos for a home topic (admin-selected & ordered).
     * Public endpoint (no auth).
     */
    public function videos(string $id, Request $request): JsonResponse
    {
        $limit = (int)($request->query('limit', 10));
        $limit = max(10, min($limit, 30));

        $topic = HomeTopic::query()
            ->active()
            ->with('category')
            ->findOrFail($id);

        // 1) If admin curated videos exist, use them (ordered).
        $videos = $topic->videos()
            ->limit($limit)
            ->get();

        // 2) Otherwise fallback to latest published videos from the topic's category
        // so the home screen isn't empty until admin curates.
        if ($videos->isEmpty() && $topic->category) {
            $videos = $topic->category->videos()
                ->published()
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        return $this->success(VideoResource::collection($videos));
    }
}
