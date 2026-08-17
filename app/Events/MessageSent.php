<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['conversation', 'sender']);
    }

    /**
     * Broadcasts on two channels: the conversation itself (consumed by an
     * open thread) and the recipient's personal channel (consumed by the
     * sidebar to bump unread counts without subscribing to every
     * conversation).
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
            new PrivateChannel('App.Models.User.'.$this->recipientId()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    private function recipientId(): int
    {
        return $this->message->conversation->otherUserId($this->message->sender_id);
    }
}
