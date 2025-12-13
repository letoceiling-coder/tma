<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Log;

class SupportLogger
{
    /**
     * Логирование создания тикета
     */
    public static function logTicketCreated(SupportTicket $ticket, array $context = []): void
    {
        Log::channel('tickets')->info('Ticket created', array_merge([
            'ticket_id' => $ticket->id,
            'theme' => $ticket->theme,
            'status' => $ticket->status,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $context));
    }

    /**
     * Логирование отправки тикета в CRM
     */
    public static function logTicketSentToCrm(SupportTicket $ticket, bool $success, array $context = []): void
    {
        $level = $success ? 'info' : 'error';
        Log::channel('tickets')->{$level}('Ticket sent to CRM', array_merge([
            'ticket_id' => $ticket->id,
            'theme' => $ticket->theme,
            'status' => $ticket->status,
            'success' => $success,
            'crm_url' => config('app.crm_url'),
        ], $context));
    }

    /**
     * Логирование создания сообщения
     */
    public static function logMessageCreated(SupportMessage $message, array $context = []): void
    {
        Log::channel('tickets')->info('Message created', array_merge([
            'message_id' => $message->id,
            'ticket_id' => $message->ticket_id,
            'sender' => $message->sender,
            'has_attachments' => !empty($message->attachments),
            'attachments_count' => is_array($message->attachments) ? count($message->attachments) : 0,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование изменения статуса тикета
     */
    public static function logTicketStatusChanged(SupportTicket $ticket, string $oldStatus, string $newStatus, array $context = []): void
    {
        Log::channel('tickets')->info('Ticket status changed', array_merge([
            'ticket_id' => $ticket->id,
            'theme' => $ticket->theme,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $context['changed_by'] ?? (auth()->check() ? 'admin' : 'crm'),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование получения сообщения от CRM
     */
    public static function logMessageReceivedFromCrm(SupportMessage $message, array $context = []): void
    {
        Log::channel('tickets')->info('Message received from CRM', array_merge([
            'message_id' => $message->id,
            'ticket_id' => $message->ticket_id,
            'has_attachments' => !empty($message->attachments),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $context));
    }

    /**
     * Логирование ошибки
     */
    public static function logError(string $action, \Exception $e, array $context = []): void
    {
        Log::channel('tickets')->error("Error: {$action}", array_merge([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование получения списка тикетов
     */
    public static function logTicketsListed(array $filters, int $count, array $context = []): void
    {
        Log::channel('tickets')->debug('Tickets list requested', array_merge([
            'filters' => $filters,
            'count' => $count,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование получения тикета
     */
    public static function logTicketViewed(SupportTicket $ticket, array $context = []): void
    {
        Log::channel('tickets')->debug('Ticket viewed', array_merge([
            'ticket_id' => $ticket->id,
            'theme' => $ticket->theme,
            'status' => $ticket->status,
            'messages_count' => $ticket->messages()->count(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование попытки отправки в закрытый тикет
     */
    public static function logClosedTicketMessageAttempt(SupportTicket $ticket, array $context = []): void
    {
        Log::channel('tickets')->warning('Attempt to send message to closed ticket', array_merge([
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование валидации
     */
    public static function logValidationError(string $action, array $errors, array $context = []): void
    {
        Log::channel('tickets')->warning("Validation error: {$action}", array_merge([
            'errors' => $errors,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ], $context));
    }

    /**
     * Логирование неавторизованного доступа
     */
    public static function logUnauthorizedAccess(string $action, array $context = []): void
    {
        Log::channel('tickets')->warning("Unauthorized access: {$action}", array_merge([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ], $context));
    }

    /**
     * Логирование webhook запроса
     */
    public static function logWebhookRequest(string $type, array $data, bool $success, array $context = []): void
    {
        $level = $success ? 'info' : 'error';
        Log::channel('tickets')->{$level}("Webhook: {$type}", array_merge([
            'type' => $type,
            'data' => $data,
            'success' => $success,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $context));
    }
}

