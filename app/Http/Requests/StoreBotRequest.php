<?php

namespace App\Http\Requests;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->attributes->get('account') ?? current_account();

        return $account && $this->user() && (
            (int) $account->owner_id === (int) $this->user()->id ||
            $account->users()->where('user_id', $this->user()->id)->where('role', 'admin')->exists()
        );
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:draft,active,paused',
            'applies_to' => 'required|array',
            'applies_to.all_connections' => 'boolean',
            'applies_to.connection_ids' => 'array',
            'applies_to.connection_ids.*' => 'integer',
            'stop_on_first_flow' => 'sometimes|boolean',
            'session_timeout_minutes' => 'sometimes|integer|min:5|max:10080',
            'session_resume_mode' => 'sometimes|string|in:resume,restart,expire',
            'session_expired_message' => 'nullable|string|max:1000',
            'starter_flow_mode' => 'sometimes|string|in:guided,empty',
            'starter_trigger_type' => 'sometimes|string|in:inbound_message,keyword',
            'starter_keywords' => 'nullable|string|max:500',
            'starter_reply_message' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $account = $this->attributes->get('account') ?? current_account();
            if (! $account) {
                return;
            }
            $appliesTo = $this->input('applies_to', []);
            $allConnections = (bool) ($appliesTo['all_connections'] ?? false);
            $connectionIds = $appliesTo['connection_ids'] ?? [];
            if (! $allConnections && empty($connectionIds)) {
                $validator->errors()->add('applies_to.connection_ids', 'Select at least one connection or enable "All connections".');

                return;
            }
            if (! empty($connectionIds)) {
                $count = WhatsAppConnection::where('account_id', $account->id)
                    ->whereIn('id', $connectionIds)
                    ->count();
                if ($count !== count($connectionIds)) {
                    $validator->errors()->add('applies_to.connection_ids', 'One or more selected connections are invalid.');
                }
            }
        });
    }
}
