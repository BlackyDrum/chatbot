<?php

namespace App\Rules;

use App\Models\Conversations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class ValidateConversationOwner implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conversation = Conversations::query()->find($value);

        if ($conversation->user_id !== Auth::id()) {
            $fail('The selected conversation id is invalid.');
        }
    }
}