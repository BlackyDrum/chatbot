<?php

namespace App\Http\Controllers;

use App\Models\Agents;
use App\Models\Collections;
use App\Models\Conversations;
use App\Models\Messages;
use App\Models\Modules;
use App\Models\SharedConversations;
use App\Rules\ValidateConversationOwner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function createConversation(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:' . config('chat.max_message_length')
        ]);

        if (!Auth::user()->module_id) {
            Log::warning('App: User with ID {user-id} is not associated with a module');

            return response()->json('You are not associated with a module. Try to login again.',500);
        }

        $module = Modules::query()->find(Auth::user()->module_id);

        $agent = Agents::query()
            ->where('module_id', '=', $module->id)
            ->where('active', '=', true)
            ->first();

        if (!$agent) {
            Log::critical('App: Failed to find active agent for module with ID {module-id}', [
                'module-id' => $module->id,
            ]);

            return response()->json('Internal Server Error',500);
        }

        $count = Conversations::query()
            ->where('user_id', '=', Auth::id())
            ->count();

        $conversation = Conversations::query()->create([
            'name' => 'Chat #' . ($count + 1),
            'url_id' => Str::random(40),
            'agent_id' => $agent->id,
            'user_id' => Auth::id(),
        ]);

        $conversationID = $conversation->url_id;

        $collection = Collections::query()
            ->where('module_id', '=', $module->id)
            ->first();

        if (!$collection) {
            Log::critical('App: Failed to find a collection for module with ID {module-id}', [
                'module-id' => $module->id
            ]);

            $conversation->delete();
            return response()->json(['message' => 'Internal Server Error'], 500);
        }


        // Capture the current timestamp here before adding entries to the 'conversation_has_document'
        // table within 'createPromptWithContext'. This step is crucial for accurately identifying
        // and deleting these entries once they fall outside the context window, ensuring they are
        // correctly timed in relation to the conversation's flow.
        $now = Carbon::now();

        try {
            $promptWithContext = ChromaController::createPromptWithContext($collection->name, $request->input('message'), $conversationID);
        } catch (\Exception $exception) {
            Log::error('ChromaDB: Failed to create prompt with context. Reason: {message}', [
                'message' => $exception->getMessage(),
                'collection' => $collection->name,
                'conversation-id' => $conversationID
            ]);

            $conversation->delete();
            return response()->json(['message' => 'Internal Server Error'], 500);
        }

        $response = ChatController::sendMessageToOpenAI($agent->instructions, $promptWithContext);

        if ($response->failed()) {
            Log::error('OpenAI: Failed to send message. Reason: {reason}. Status: {status}', [
                'reason' => $response->reason(),
                'status' => $response->status(),
            ]);

            $conversation->delete();
            return response()->json($response->reason(), $response->status());
        }

        Messages::query()->create([
            'user_message' => $request->input('message'),
            'agent_message' => htmlspecialchars($response->json()['choices'][0]['message']['content']),
            'user_message_with_context' => $promptWithContext,
            'prompt_tokens' => $response->json()['usage']['prompt_tokens'],
            'completion_tokens' => $response->json()['usage']['completion_tokens'],
            'conversation_id' => $conversation->id,
            'created_at' => $now
        ]);

        Log::info('App: User with ID {user-id} created a new conversation', [
            'conversation-id' => $conversationID
        ]);

        return response()->json(['id' => $conversationID]);
    }

    public function deleteConversation(Request $request)
    {
        $request->validate([
            'conversation_id' => ['bail', 'required', 'string', 'exists:conversations,url_id', new ValidateConversationOwner()]
        ]);

        Conversations::query()
            ->where('url_id', '=', $request->input('conversation_id'))
            ->delete();

        Log::info('App: User with ID {user-id} deleted a conversation with ID {conversation-id}', [
            'conversation-id' => $request->input('conversation_id')
        ]);

        return response()->json(['id' => $request->input('conversation_id')]);
    }

    public function renameConversation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:64',
            'conversation_id' => ['bail', 'required', 'string', 'exists:conversations,url_id', new ValidateConversationOwner()]
        ]);

        Conversations::query()
            ->where('url_id', '=', $request->input('conversation_id'))
            ->update([
                'name' => $request->input('name')
            ]);

        Log::info('User with ID {user-id} renamed a conversation with ID {conversation-id}', [
            'new-name' => $request->input('name'),
            'conversation-id' => $request->input('conversation_id')
        ]);

        return response()->json(['name' => $request->input('name'), 'id' => $request->input('conversation_id')]);
    }

    public function share(string $id) {
        $shared = SharedConversations::query()
            ->where('shared_conversations.shared_url_id', '=', $id)
            ->first();

        if (!$shared) {
            Log::info('App: User with ID {user-id} tried to access an invalid, shared conversation', [
                'shared-conversation-url-id' => $id
            ]);

            return redirect('/');
        }

        $name = Conversations::query()
            ->find($shared->conversation_id)
            ->name;

        $messages = SharedConversations::query()
            ->join('conversations', 'conversations.id', '=', 'shared_conversations.conversation_id')
            ->join('messages', 'messages.conversation_id', '=', 'conversations.id')
            ->where('shared_conversations.shared_url_id', '=', $id)
            ->whereRaw('messages.created_at < shared_conversations.created_at')
            ->select([
                'messages.user_message',
                'messages.agent_message',
            ])
            ->get();

        return Inertia::render('Chat', [
            'messages' => $messages,
            'conversation_id' => $id,
            'conversation_name' => $name,
            'hasPrompt' => false,
        ]);
    }

    public function createShare(Request $request)
    {
        $request->validate([
            'conversation_id' => ['bail', 'required', 'string', 'exists:conversations,url_id', new ValidateConversationOwner()]
        ]);

        $conversation = Conversations::query()
            ->where('url_id', '=', $request->input('conversation_id'))
            ->first();

        $sharedConversation = SharedConversations::query()
            ->where('conversation_id', '=', $conversation->id)
            ->first();

        if ($sharedConversation) {
            return response()->json(['message' => 'You have shared this conversation already'], 409);
        }

        $sharedConversation = SharedConversations::query()->create([
            'shared_url_id' => Str::random(40),
            'conversation_id' => $conversation->id,
        ]);

        Log::info('User with ID {user-id} shared a conversation', [
            'shared_url_id' => $sharedConversation->shared_url_id,
            'conversation_id' => $conversation->id,
        ]);

        return response()->json(['shared_url_id' => $sharedConversation->shared_url_id]);
    }

    public function deleteShare(Request $request)
    {
        $request->validate([
            'conversation_id' => ['bail', 'required', 'string', 'exists:conversations,url_id', new ValidateConversationOwner()]
        ]);

        $conversation = Conversations::query()
            ->where('url_id', '=', $request->input('conversation_id'))
            ->first();

        SharedConversations::query()
            ->where('conversation_id', '=', $conversation->id)
            ->delete();

        Log::info('User with ID {user-id} deleted a shared conversation', [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function peek(string $id)
    {
        $conversation = Conversations::query()
            ->where('url_id', $id)
            ->first();

        if (!$conversation) {
            return redirect('/');
        }

        $messages = Messages::query()
            ->where('conversation_id', '=', $conversation->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Chat', [
            'messages' => $messages,
            'conversation_id' => $id,
            'conversation_name' => $conversation->name,
            'hasPrompt' => false,
        ]);
    }
}
