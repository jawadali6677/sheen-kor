<?php

namespace App\Http\Controllers;

use App\Actions\CreateGroupConversation;
use App\Actions\FindOrCreateDirectConversation;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $user = $request->user();

        return view('messages.index', [
            'conversations' => $this->conversationsFor($user),
            'conversation' => null,
            'messages' => collect(),
            'addableUsers' => collect(),
            'followings' => $this->followingsFor($user),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $conversation->load('participants');

        if ($conversation->hasParticipant($user)) {
            $conversation->participants()->updateExistingPivot($user->id, [
                'last_read_at' => now(),
            ]);
        }

        $messages = $conversation->messages()
            ->with('user')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $addableUsers = collect();

        if ($user->can('addParticipants', $conversation)) {
            $addableUsers = $user->followings()
                ->where('users.status', true)
                ->whereKeyNot($conversation->participants->pluck('id'))
                ->orderBy('name')
                ->limit(100)
                ->get();
        }

        return view('messages.show', [
            'conversations' => $this->conversationsFor($user),
            'conversation' => $conversation,
            'messages' => $messages,
            'addableUsers' => $addableUsers,
            'followings' => $this->followingsFor($user),
        ]);
    }

    public function storeDirect(Request $request, FindOrCreateDirectConversation $findOrCreate): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $other = User::query()->findOrFail($validated['user_id']);
        $conversation = $findOrCreate->handle($request->user(), $other);

        return redirect()->route('messages.show', $conversation);
    }

    public function createGroup(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $users = $request->user()
            ->followings()
            ->where('users.status', true)
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('messages.create-group', [
            'users' => $users,
        ]);
    }

    public function storeGroup(Request $request, CreateGroupConversation $createGroup): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'user_ids' => ['required', 'array', 'min:2'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $conversation = $createGroup->handle(
            $request->user(),
            trim($validated['title']),
            $validated['user_ids'],
        );

        return redirect()->route('messages.show', $conversation);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()
            ->route('messages.index')
            ->with('success', 'The group has been deleted.');
    }

    /**
     * @return LengthAwarePaginator<int, Conversation>
     */
    private function conversationsFor(User $user): LengthAwarePaginator
    {
        return Conversation::query()
            ->with(['participants', 'latestMessage.user'])
            ->withCount(['messages as unread_count' => function ($query) use ($user): void {
                $query->where('messages.user_id', '!=', $user->id)
                    ->whereExists(function ($query) use ($user): void {
                        $query->selectRaw('1')
                            ->from('conversation_user')
                            ->whereColumn('conversation_user.conversation_id', 'messages.conversation_id')
                            ->where('conversation_user.user_id', $user->id)
                            ->where(function ($query): void {
                                $query->whereNull('conversation_user.last_read_at')
                                    ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
                            });
                    });
            }])
            ->where(function ($query) use ($user): void {
                $query->whereHas('participants', function ($query) use ($user): void {
                    $query->where('users.id', $user->id);
                });

                if ($user->isAdmin()) {
                    $query->orWhere('type', ConversationType::Group);
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('conversations.id')
            ->paginate(20);
    }

    /**
     * @return Collection<int, User>
     */
    private function followingsFor(User $user)
    {
        return $user->followings()
            ->where('users.status', true)
            ->orderBy('name')
            ->limit(100)
            ->get();
    }
}
