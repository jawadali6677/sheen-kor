<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateComment;
use App\Actions\DeleteComment;
use App\Actions\ListComments;
use App\Actions\UpdateComment;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function indexPost(Request $request, Post $post, ListComments $listComments): JsonResponse
    {
        return $this->engagementResponse($listComments->handle($request->user(), $post));
    }

    public function storePost(Request $request, Post $post, CreateComment $createComment): JsonResponse
    {
        return $this->engagementResponse($createComment->handle($request, $request->user(), $post));
    }

    public function indexAlert(Request $request, Alert $alert, ListComments $listComments): JsonResponse
    {
        return $this->engagementResponse($listComments->handle($request->user(), $alert));
    }

    public function storeAlert(Request $request, Alert $alert, CreateComment $createComment): JsonResponse
    {
        return $this->engagementResponse($createComment->handle($request, $request->user(), $alert));
    }

    public function update(Request $request, Comment $comment, UpdateComment $updateComment): JsonResponse
    {
        $this->authorize('update', $comment);

        return $this->engagementResponse($updateComment->handle($request, $request->user(), $comment));
    }

    public function destroy(Comment $comment, DeleteComment $deleteComment): JsonResponse
    {
        $this->authorize('delete', $comment);

        return $this->engagementResponse($deleteComment->handle($comment));
    }

    /**
     * @param  array{payload: array<string, mixed>, status: int}  $result
     */
    private function engagementResponse(array $result): JsonResponse
    {
        return response()->json($result['payload'], $result['status']);
    }
}
