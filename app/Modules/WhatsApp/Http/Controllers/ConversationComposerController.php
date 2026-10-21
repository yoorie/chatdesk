<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\ConversationComposerActionService;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Http\Request;

class ConversationComposerController extends Controller
{
    public function __construct(
        protected ConversationComposerActionService $actions
    ) {}

    public function sendMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendMessage($request, $conversation);
    }

    public function retryMessage(Request $request, WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        return $this->actions->retryMessage($request, $conversation, $message);
    }

    public function sendTemplateMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendTemplateMessage($request, $conversation);
    }

    public function sendMediaMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendMediaMessage($request, $conversation);
    }

    public function sendReaction(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendReaction($request, $conversation);
    }

    public function sendLocationMessage(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendLocationMessage($request, $conversation);
    }

    public function sendList(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendList($request, $conversation);
    }

    public function sendInteractiveButtons(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendInteractiveButtons($request, $conversation);
    }

    public function sendFlow(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendFlow($request, $conversation);
    }

    public function sendCtaUrl(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendCtaUrl($request, $conversation);
    }

    public function sendPaymentLink(Request $request, WhatsAppConversation $conversation, RazorpayPaymentLinkService $paymentLinks)
    {
        return $this->actions->sendPaymentLink($request, $conversation, $paymentLinks);
    }

    public function sendContactCard(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendContactCard($request, $conversation);
    }

    public function sendProduct(Request $request, WhatsAppConversation $conversation)
    {
        return $this->actions->sendProduct($request, $conversation);
    }
}
