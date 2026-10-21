<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppTemplate;

class TemplateComposer
{
    /**
     * Prepare template message payload for Meta API.
     */
    public function preparePayload(
        WhatsAppTemplate $template,
        string $toWaId,
        array $variables = []
    ): array {
        // Validate variable count
        $requiredVariables = $this->extractRequiredVariables($template);
        $providedCount = count($variables);

        if ($providedCount < $requiredVariables['total']) {
            throw new \Exception(
                "Template requires {$requiredVariables['total']} variables, but {$providedCount} provided"
            );
        }

        // Build components array for Meta API
        $components = [];

        // Header variables (if TEXT header with variables)
        if ($template->header_type === 'TEXT' && $template->header_text) {
            $headerVars = $this->extractVariables($template->header_text);
            if (count($headerVars) > 0) {
                $headerParams = [];
                foreach ($headerVars as $varIndex) {
                    $varValue = $variables[$varIndex - 1] ?? '';
                    $headerParams[] = ['type' => 'text', 'text' => $varValue];
                }
                $components[] = [
                    'type' => 'header',
                    'parameters' => $headerParams];
            }
        }

        // Body variables
        if ($template->body_text) {
            $bodyVars = $this->extractVariables($template->body_text);
            if (count($bodyVars) > 0) {
                $bodyParams = [];
                foreach ($bodyVars as $varIndex) {
                    $varValue = $variables[$varIndex - 1] ?? '';
                    $bodyParams[] = ['type' => 'text', 'text' => $varValue];
                }
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParams];
            }
        }

        // Button variables (for URL buttons with dynamic suffix)
        if ($template->has_buttons) {
            $buttonParams = [];
            $buttonIndex = 0;

            foreach ($template->buttons as $button) {
                if ($button['type'] === 'URL' && isset($button['url'])) {
                    // Check if URL has variable placeholder
                    if (strpos($button['url'], '{{1}}') !== false) {
                        $varValue = $variables[$requiredVariables['body_count'] + $buttonIndex] ?? '';
                        $buttonParams[] = [
                            'type' => 'text',
                            'text' => $varValue,
                            'sub_type' => 'url',
                            'index' => (string) $buttonIndex];
                        $buttonIndex++;
                    }
                }
            }

            if (count($buttonParams) > 0) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => $buttonParams];
            }
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toWaId,
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => [
                    'code' => $template->language],
                'components' => $components]];
    }

    /**
     * Extract required variables from template.
     */
    public function extractRequiredVariables(WhatsAppTemplate $template): array
    {
        $headerVars = [];
        $bodyVars = [];
        $buttonVars = 0;

        if ($template->header_type === 'TEXT' && $template->header_text) {
            $headerVars = $this->extractVariables($template->header_text);
        }

        if ($template->body_text) {
            $bodyVars = $this->extractVariables($template->body_text);
        }

        // Count URL button variables
        if ($template->has_buttons) {
            foreach ($template->buttons as $button) {
                if ($button['type'] === 'URL' && isset($button['url'])) {
                    if (strpos($button['url'], '{{1}}') !== false) {
                        $buttonVars++;
                    }
                }
            }
        }

        return [
            'header' => $headerVars,
            'body' => $bodyVars,
            'button' => $buttonVars,
            'header_count' => count($headerVars),
            'body_count' => count($bodyVars),
            'button_count' => $buttonVars,
            'total' => count($headerVars) + count($bodyVars) + $buttonVars];
    }

    /**
     * Extract variable indices from text (e.g., {{1}}, {{2}}).
     */
    protected function extractVariables(string $text): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);
        $indices = array_map('intval', $matches[1] ?? []);

        return array_unique($indices);
    }

    /**
     * Render template preview with variables filled.
     */
    public function renderPreview(WhatsAppTemplate $template, array $variables = []): array
    {
        $preview = [
            'header' => null,
            'body' => null,
            'footer' => null,
            'buttons' => []];

        // Header
        if ($template->header_type === 'TEXT' && $template->header_text) {
            $preview['header'] = $this->replaceVariables($template->header_text, $variables);
        }

        // Body
        if ($template->body_text) {
            $preview['body'] = $this->replaceVariables($template->body_text, $variables);
        }

        // Footer
        if ($template->footer_text) {
            $preview['footer'] = $template->footer_text;
        }

        // Buttons
        if ($template->has_buttons) {
            foreach ($template->buttons as $button) {
                $buttonPreview = [
                    'type' => $button['type'],
                    'text' => $button['text'] ?? ''];

                if ($button['type'] === 'URL' && isset($button['url'])) {
                    $buttonPreview['url'] = $this->replaceVariables($button['url'], $variables);
                } elseif ($button['type'] === 'PHONE_NUMBER' && isset($button['phone_number'])) {
                    $buttonPreview['phone_number'] = $button['phone_number'];
                }

                $preview['buttons'][] = $buttonPreview;
            }
        }

        return $preview;
    }

    /**
     * Replace variables in text with values.
     */
    protected function replaceVariables(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', function ($matches) use ($variables) {
            $index = (int) $matches[1] - 1;

            return $variables[$index] ?? $matches[0];
        }, $text);
    }
}
