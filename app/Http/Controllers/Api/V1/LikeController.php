<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LikeContent;
use App\Actions\UnlikeContent;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function storePost(Request $request, Post $post, LikeContent $likeContent): JsonResponse
    {
        return $this->engagementResponse($likeContent->handle($request->user(), $post));
    }

    public function destroyPost(Request $request, Post $post, UnlikeContent $unlikeContent): JsonResponse
    {
        return $this->engagementResponse($unlikeContent->handle($request->user(), $post));
    }

    public function storeAlert(Request $request, Alert $alert, LikeContent $likeContent): JsonResponse
    {
        return $this->engagementResponse($likeContent->handle($request->user(), $alert));
    }

    public function destroyAlert(Request $request, Alert $alert, UnlikeContent $unlikeContent): JsonResponse
    {
        return $this->engagementResponse($unlikeContent->handle($request->user(), $alert));
    }

    /**
     * @param  array{payload: array<string, mixed>, status: int}  $result
     */
    private function engagementResponse(array $result): JsonResponse
    {
        return response()->json($result['payload'], $result['status']);
    }
}
