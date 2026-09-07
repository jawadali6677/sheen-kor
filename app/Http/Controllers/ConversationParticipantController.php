<?php

namespace App\Http\Controllers;

use App\Actions\AddConversationParticipants;
use App\Actions\LeaveConversation;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationParticipantController extends Controller
{
    public function store(
        Request $request,
        Conversation $conversation,
        AddConversationParticipants $addParticipants,
    ): RedirectResponse {
        $this->authorize('addParticipants', $conversation);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $addParticipants->handle($request->user(), $conversation, $validated['user_ids']);

        return redirect()
            ->route('messages.show', $conversation)
            ->with('success', 'People were added to the group.');
    }

    public function destroy(Request $request, Conversation $conversation, LeaveConversation $leave): RedirectResponse
    {
        $this->authorize('leave', $conversation);

        $leave->handle($request->user(), $conversation);

        return redirect()
            ->route('messages.index')
            ->with('success', 'You left the conversation.');
    }

    public function remove(
        Conversation $conversation,
        User $user,
        LeaveConversation $leave,
    ): RedirectResponse {
        $this->authorize('removeParticipant', $conversation);

        if (! $conversation->hasParticipant($user)) {
            return redirect()
                ->route('messages.show', $conversation)
                ->with('error', 'That person is not in this group.');
        }

        $leave->handle($user, $conversation);

        if (! Conversation::query()->whereKey($conversation->id)->exists()) {
            return redirect()
                ->route('messages.index')
                ->with('success', 'The group was deleted because no members remained.');
        }

        return redirect()
            ->route('messages.show', $conversation)
            ->with('success', $user->name.' was removed from the group.');
    }
}
