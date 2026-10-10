<?php

namespace App\Http\Controllers;

use App\Actions\LikeContent;
use App\Actions\UnlikeContent;
use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LikeController extends Controller
{
    public function storePost(Post $post, LikeContent $likeContent): JsonResponse
    {
        return $this->engagementResponse($likeContent->handle($this->actor(), $post));
    }

    public function destroyPost(Post $post, UnlikeContent $unlikeContent): JsonResponse
    {
        return $this->engagementResponse($unlikeContent->handle($this->actor(), $post));
    }

    public function storeAlert(Alert $alert, LikeContent $likeContent): JsonResponse
    {
        return $this->engagementResponse($likeContent->handle($this->actor(), $alert));
    }

    public function destroyAlert(Alert $alert, UnlikeContent $unlikeContent): JsonResponse
    {
        return $this->engagementResponse($unlikeContent->handle($this->actor(), $alert));
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * @param  array{payload: array<string, mixed>, status: int}  $result
     */
    private function engagementResponse(array $result): JsonResponse
    {
        return response()->json($result['payload'], $result['status']);
    }
}
