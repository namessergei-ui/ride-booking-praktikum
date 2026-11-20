<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request, Trip $trip): JsonResponse
    {
        abort_unless($trip->hasParticipant($request->user()), 403, 'Trip membership is required.');
        $data = $request->validate(['after_id' => ['sometimes', 'required', 'integer', 'min:0']]);

        $query = $trip->messages()->with('author:id,name');
        if (isset($data['after_id'])) {
            $messages = $query->where('id', '>', $data['after_id'])->orderBy('id')->limit(50)->get();
        } else {
            $messages = $query->latest('id')->limit(50)->get()->reverse()->values();
        }

        return response()->json(['data' => $messages->map($this->serialize(...))]);
    }

    public function store(Request $request, Trip $trip): JsonResponse
    {
        abort_unless($trip->hasParticipant($request->user()), 403, 'Trip membership is required.');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = DB::transaction(function () use ($trip, $request, $data): ChatMessage {
            // Commit messages in ID order so polling via after_id cannot skip a concurrent write.
            $lockedTrip = Trip::lockForUpdate()->findOrFail($trip->id);

            return $lockedTrip->messages()->create(['author_id' => $request->user()->id, 'body' => $data['body']]);
        });

        return response()->json(['data' => $this->serialize($message->load('author:id,name'))], 201);
    }

    private function serialize(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'author' => $message->author->only('id', 'name'),
            'created_at' => $message->created_at->toAtomString(),
        ];
    }
}
