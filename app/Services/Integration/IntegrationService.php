<?php

namespace App\Services\Integration;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
    protected string $crmUrl;
    protected ?string $deployToken;
    protected string $projectIdentifier;

    public function __construct()
    {
        $this->crmUrl = config('app.crm_url', env('APP_CRM_URL', 'https://crm.siteaccess.ru'));
        $this->deployToken = config('app.deploy_token') ?: env('DEPLOY_TOKEN') ?: null;
        $this->projectIdentifier = config('app.project_identifier', env('APP_PROJECT_IDENTIFIER', 'tma'));
    }

    /**
     * Отправить тикет в CRM
     */
    public function sendTicketToCrm(SupportTicket $ticket, array $attachments = []): ?string
    {
        if (!$this->deployToken) {
            Log::channel('tickets')->error('DEPLOY_TOKEN not configured');
            return null;
        }

        try {
            $url = rtrim($this->crmUrl, '/') . '/api/integration/tickets';
            
            $payload = [
                'external_ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'message' => $ticket->messages()->first()?->body ?? '',
                'attachments' => $attachments,
            ];

            $response = Http::withToken($this->deployToken)
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $crmTicketId = $data['data']['id'] ?? null;
                
                if ($crmTicketId) {
                    $ticket->update(['external_id' => $crmTicketId]);
                }

                Log::channel('tickets')->info('Ticket sent to CRM', [
                    'ticket_id' => $ticket->id,
                    'crm_ticket_id' => $crmTicketId,
                ]);

                return $crmTicketId;
            }

            Log::channel('tickets')->error('Failed to send ticket to CRM', [
                'ticket_id' => $ticket->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::channel('tickets')->error('Exception sending ticket to CRM', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Отправить сообщение в CRM
     */
    public function sendMessageToCrm(SupportMessage $message): bool
    {
        if (!$this->deployToken) {
            return false;
        }

        $ticket = $message->ticket;
        
        if (!$ticket->external_id) {
            Log::channel('tickets')->warning('Cannot send message: ticket has no external_id', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
            ]);
            return false;
        }

        // Проверка на дубликат
        if ($message->external_message_id) {
            Log::channel('tickets')->debug('Message already sent to CRM', [
                'message_id' => $message->id,
                'external_message_id' => $message->external_message_id,
            ]);
            return true;
        }

        try {
            $url = rtrim($this->crmUrl, '/') . '/api/integration/messages';
            
            $payload = [
                'external_ticket_id' => $ticket->external_id,
                'message' => $message->body ?? $message->message ?? '',
                'attachments' => $message->attachments ?? [],
                'sender' => 'tma',
                'external_message_id' => $message->id,
            ];

            $response = Http::withToken($this->deployToken)
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $crmMessageId = $data['data']['id'] ?? null;
                
                if ($crmMessageId) {
                    $message->update(['external_message_id' => $crmMessageId]);
                }

                Log::channel('tickets')->info('Message sent to CRM', [
                    'message_id' => $message->id,
                    'crm_message_id' => $crmMessageId,
                ]);

                return true;
            }

            Log::channel('tickets')->error('Failed to send message to CRM', [
                'message_id' => $message->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::channel('tickets')->error('Exception sending message to CRM', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

