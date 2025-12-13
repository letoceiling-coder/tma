<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\WebhookMessageRequest;
use App\Http\Requests\WebhookStatusRequest;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportService;
use App\Services\SupportLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    protected SupportService $supportService;

    public function __construct(SupportService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * Get list of tickets
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $perPage = $request->get('per_page', 20);

        $query = SupportTicket::with(['messages' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }])->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate($perPage);

        SupportLogger::logTicketsListed(
            ['status' => $status, 'per_page' => $perPage],
            $tickets->total()
        );

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Get single ticket with messages
     */
    public function show(string $id): JsonResponse
    {
        $ticket = SupportTicket::with(['messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        SupportLogger::logTicketViewed($ticket);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Create new ticket
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create ticket
            $ticket = SupportTicket::create([
                'theme' => $request->input('theme'),
                'status' => 'open',
            ]);

            // Process attachments
            $attachments = [];
            if ($request->hasFile('attachments')) {
                $attachments = $this->supportService->processAttachments($request->file('attachments'));
            }

            // Create initial message
            $message = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'sender' => 'local',
                'message' => $request->input('message'),
                'attachments' => !empty($attachments) ? $attachments : null,
                'created_at' => now(),
            ]);

            // Логирование создания тикета и сообщения
            SupportLogger::logTicketCreated($ticket, [
                'attachments_count' => count($attachments),
                'message_length' => strlen($request->input('message')),
            ]);
            SupportLogger::logMessageCreated($message);

            // Send to CRM
            $crmAttachments = array_map(function ($att) {
                return [
                    'name' => $att['name'],
                    'url' => $att['url'],
                    'size' => $att['size'],
                    'mime_type' => $att['mime_type'],
                ];
            }, $attachments);

            $crmSent = $this->supportService->sendTicketToCrm($ticket, $crmAttachments);
            SupportLogger::logTicketSentToCrm($ticket, $crmSent, [
                'attachments_count' => count($crmAttachments),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Тикет успешно создан',
                'data' => $ticket->load('messages'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            SupportLogger::logError('Creating ticket', $e, [
                'theme' => $request->input('theme'),
                'has_attachments' => $request->hasFile('attachments'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании тикета',
            ], 500);
        }
    }

    /**
     * Send message in ticket
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $ticket = SupportTicket::findOrFail($request->input('ticket_id'));

            // Check if chat is enabled
            if (!$ticket->isChatEnabled()) {
                SupportLogger::logClosedTicketMessageAttempt($ticket);
                return response()->json([
                    'success' => false,
                    'message' => 'Чат недоступен для закрытых тикетов',
                ], 403);
            }

            DB::beginTransaction();

            // Process attachments
            $attachments = [];
            if ($request->hasFile('attachments')) {
                $attachments = $this->supportService->processAttachments($request->file('attachments'));
            }

            // Create message
            $message = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'sender' => 'local',
                'message' => $request->input('message'),
                'attachments' => !empty($attachments) ? $attachments : null,
                'created_at' => now(),
            ]);

            // Логирование создания сообщения
            SupportLogger::logMessageCreated($message, [
                'message_length' => strlen($request->input('message')),
            ]);

            // Send to CRM
            $crmAttachments = array_map(function ($att) {
                return [
                    'name' => $att['name'],
                    'url' => $att['url'],
                    'size' => $att['size'],
                    'mime_type' => $att['mime_type'],
                ];
            }, $attachments);

            // TODO: Send message to CRM if needed

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Сообщение отправлено',
                'data' => $message,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            SupportLogger::logError('Sending message', $e, [
                'ticket_id' => $request->input('ticket_id'),
                'has_attachments' => $request->hasFile('attachments'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке сообщения',
            ], 500);
        }
    }

    /**
     * Webhook: Receive message from CRM
     */
    public function webhookMessage(WebhookMessageRequest $request): JsonResponse
    {
        try {
            $ticket = SupportTicket::findOrFail($request->input('ticket_id'));

            DB::beginTransaction();

            $message = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'sender' => 'crm',
                'message' => $request->input('message'),
                'attachments' => $request->input('attachments'),
                'created_at' => now(),
            ]);

            // Логирование получения сообщения от CRM
            SupportLogger::logMessageReceivedFromCrm($message, [
                'message_length' => strlen($request->input('message')),
            ]);
            SupportLogger::logWebhookRequest('message', [
                'ticket_id' => $ticket->id,
                'has_attachments' => !empty($request->input('attachments')),
            ], true);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Сообщение получено',
                'data' => $message,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            SupportLogger::logError('Processing webhook message', $e, [
                'ticket_id' => $request->input('ticket_id'),
            ]);
            SupportLogger::logWebhookRequest('message', $request->all(), false, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обработке сообщения',
            ], 500);
        }
    }

    /**
     * Webhook: Change ticket status from CRM
     */
    public function webhookStatus(WebhookStatusRequest $request): JsonResponse
    {
        try {
            $ticket = SupportTicket::findOrFail($request->input('ticket_id'));

            DB::beginTransaction();

            $oldStatus = $ticket->status;
            $newStatus = $request->input('status');
            
            $ticket->status = $newStatus;
            $ticket->save();

            // Логирование изменения статуса
            SupportLogger::logTicketStatusChanged($ticket, $oldStatus, $newStatus, [
                'changed_by' => 'crm',
            ]);
            SupportLogger::logWebhookRequest('status', [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ], true);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Статус обновлен',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            SupportLogger::logError('Updating ticket status', $e, [
                'ticket_id' => $request->input('ticket_id'),
                'new_status' => $request->input('status'),
            ]);
            SupportLogger::logWebhookRequest('status', $request->all(), false, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении статуса',
            ], 500);
        }
    }
}

