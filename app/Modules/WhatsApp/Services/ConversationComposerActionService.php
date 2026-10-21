<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Http\Controllers\ConversationController;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Http\Request;

class ConversationComposerActionService
{
    public function __construct(
        protected ConversationController $legacyController
    ) {}

    public function sendMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendMessage($request, $conversation);
    }

    public function retryMessage(Request $request, WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        return $this->legacyController->retryMessage($request, $conversation, $message);
    }

    public function sendTemplateMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendTemplateMessage($request, $conversation);
    }

    public function sendMediaMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendMediaMessage($request, $conversation);
    }

    public function sendReaction(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendReaction($request, $conversation);
    }

    public function sendLocationMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendLocationMessage($request, $conversation);
    }

    public function sendList(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendList($request, $conversation);
    }

    public function sendInteractiveButtons(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendInteractiveButtons($request, $conversation);
    }

    public function sendFlow(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendFlow($request, $conversation);
    }

    public function sendCtaUrl(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendCtaUrl($request, $conversation);
    }

    public function sendPaymentLink(Request $request, WhatsAppConversation $conversation, RazorpayPaymentLinkService $paymentLinks)
    {
        return $this->legacyController->sendPaymentLink($request, $conversation, $paymentLinks);
    }

    public function sendContactCard(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendContactCard($request, $conversation);
    }

    public function sendProduct(Request $request, WhatsAppConversation $conversation)
    {
        return $this->legacyController->sendProduct($request, $conversation);
    }
}
