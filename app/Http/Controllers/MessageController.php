<?php

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\ConversationMessageReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'before_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $messages = $conversation->messages()
            ->with('user')
            ->when(
                isset($validated['before_id']),
                fn ($query) => $query->where('id', '<', $validated['before_id']),
            )
            ->when(
                isset($validated['after_id']),
                fn ($query) => $query->where('id', '>', $validated['after_id']),
            )
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'messages' => $messages->map(fn (Message $message): array => $message->toChatPayload())->all(),
        ]);
    }

    public function store(Request $request, Conversation $conversation, SendMessage $sendMessage): JsonResponse
    {
        $this->authorize('send', $conversation);

        $request->merge([
            'body' => trim(strip_tags((string) $request->input('body'))),
        ]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $sendMessage->handle($request->user(), $conversation, $validated['body']);

        $this->broadcastSentMessage($request, $message);
        $this->notifyConversationParticipants($message);

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'chat_message' => $message->toChatPayload(),
        ], 201);
    }

    private function broadcastSentMessage(Request $request, Message $message): void
    {
        try {
            $pending = broadcast(new MessageSent($message));

            if (preg_match('/^\d+\.\d+$/', (string) $request->header('X-Socket-ID')) === 1) {
                $pending->toOthers();
            }

            unset($pending);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function notifyConversationParticipants(Message $message): void
    {
        $message->loadMissing('conversation.participants');

        foreach ($message->conversation->participants as $participant) {
            if ($participant->id === $message->user_id) {
                continue;
            }

            try {
                $participant->notifyInbox(new ConversationMessageReceived($message));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
