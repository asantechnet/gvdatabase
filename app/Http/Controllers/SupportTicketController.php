<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailDeliveryNotConfiguredException;
use App\Http\Resources\SupportTicketResource;
use App\Mail\SupportTicketCreated;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->with('attachments')
            ->latest('id')
            ->limit(min(max($request->integer('limit', 50), 1), 100))
            ->get();

        return response()->json(['success' => true, 'data' => SupportTicketResource::collection($tickets)]);
    }

    public function store(Request $request, CompanyProfile $company): JsonResponse
    {
        // Disabled delivery is a setup error, not a transport failure: no
        // transport is contacted and the client receives one stable code.
        $company->assertEmailDeliveryEnabled();
        $recipient = trim((string) config('support.ticket_recipient'));
        abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL), 503, 'business.support_recipient_not_configured');

        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', SupportTicket::CATEGORIES)],
            'severity' => ['required', 'string', 'in:'.implode(',', SupportTicket::SEVERITIES)],
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,txt,csv,doc,docx,xls,xlsx,png,jpg,jpeg,webp'],
        ]);
        $files = $request->file('attachments', []);
        $totalAttachmentBytes = collect($files)->sum(fn ($file) => $file->getSize());
        if ($totalAttachmentBytes > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachments' => ['business.support_attachment_total_exceeded'],
            ]);
        }
        unset($data['attachments']);
        $storedPaths = [];

        try {
            $ticket = DB::transaction(function () use ($data, $recipient, $request, $files, $company, &$storedPaths): SupportTicket {
                $ticket = SupportTicket::create([
                    ...$data,
                    'user_id' => $request->user()->id,
                    // A temporary unique value keeps the final, readable number
                    // collision-free even when several people submit at once.
                    'ticket_number' => 'SUP-TMP-'.Str::uuid(),
                    'status' => 'open',
                    'recipient_email' => $recipient,
                    'submitted_at' => now(),
                ]);
                $ticket->update(['ticket_number' => 'SUP-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);
                foreach ($files as $file) {
                    $disk = 'local';
                    $path = $file->storeAs("support-tickets/{$ticket->id}", Str::uuid().'.'.$file->extension(), $disk);
                    $storedPaths[] = ['disk' => $disk, 'path' => $path];
                    $ticket->attachments()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
                $ticket->load(['user', 'attachments']);

                $mailer = $company->configureMailer();
                $mail = $mailer ? Mail::mailer($mailer) : Mail::mailer(config('mail.default'));
                $mail->to($recipient)->send(new SupportTicketCreated($ticket));

                return $ticket;
            });
        } catch (EmailDeliveryNotConfiguredException $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk($storedPath['disk'])->delete($storedPath['path']);
            }

            throw $exception;
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk($storedPath['disk'])->delete($storedPath['path']);
            }
            report($exception);

            return response()->json([
                'success' => false,
                'code' => 'business.support_email_delivery_failed',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => new SupportTicketResource($ticket)], 201);
    }

    public function download(Request $request, SupportTicket $supportTicket, SupportTicketAttachment $attachment)
    {
        abort_unless($supportTicket->user_id === $request->user()->id && $attachment->support_ticket_id === $supportTicket->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
