<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Messages extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_message',
        'agent_message',
        'user_message_with_context',
        'prompt_tokens',
        'completion_tokens',
        'openai_language_model',
        'conversation_id',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'id',
        'user_message_with_context',
        'prompt_tokens',
        'completion_tokens',
        'conversation_id',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversations::class, 'conversation_id');
    }
}
