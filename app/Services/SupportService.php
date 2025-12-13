<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Services\SupportLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportService
{
    protected string $crmUrl;
    protected ?string $deployToken;
    protected ?string $projectIdentifier;

    public function __construct()
    {
        $this->crmUrl = env('APP_CRM_URL', config('app.crm_url', 'https://crm.siteaccess.ru/api/v1/tecket'));
        $this->deployToken = env('DEPLOY_TOKEN') ?: null;
        $this->projectIdentifier = env('APP_PROJECT_IDENTIFIER', config('app.project_identifier', 'default'));
    }

    /**
     * Send ticket to external CRM
     */
    public function sendTicketToCrm(SupportTicket $ticket, array $attachments = []): bool
    {
        if (!$this->deployToken) {
            Log::error('DEPLOY_TOKEN not configured');
            return false;
        }

        try {
            $payload = [
                'ticket_id' => $ticket->id,
                'theme' => $ticket->theme,
                'message' => $ticket->messages()->first()?->message ?? '',
                'attachments' => $attachments,
                'project' => $this->projectIdentifier,
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->deployToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post($this->crmUrl, $payload);

            if ($response->successful()) {
                Log::channel('tickets')->info('Ticket sent to CRM successfully', [
                    'ticket_id' => $ticket->id,
                    'theme' => $ticket->theme,
                    'crm_url' => $this->crmUrl,
                    'response_status' => $response->status(),
                    'response' => $response->json(),
                    'attachments_count' => count($attachments),
                ]);
                return true;
            }

            Log::channel('tickets')->error('Failed to send ticket to CRM', [
                'ticket_id' => $ticket->id,
                'theme' => $ticket->theme,
                'crm_url' => $this->crmUrl,
                'status' => $response->status(),
                'response' => $response->body(),
                'attachments_count' => count($attachments),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::channel('tickets')->error('Exception while sending ticket to CRM', [
                'ticket_id' => $ticket->id,
                'theme' => $ticket->theme,
                'crm_url' => $this->crmUrl,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Process attachments and return array of file info
     */
    public function processAttachments(array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $path = $file->store('support/attachments', 'public');
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'url' => asset('storage/' . $path),
            ];
        }

        return $attachments;
    }
}

