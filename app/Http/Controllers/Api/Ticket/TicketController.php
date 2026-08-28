<?php

namespace App\Http\Controllers\Api\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ticket\ChangeTicketStatusRequest;
use App\Http\Requests\Api\Ticket\CreateTicketCommentRequest;
use App\Http\Requests\Api\Ticket\CreateTicketRequest;
use App\Http\Requests\Api\Ticket\ListTicketsRequest;
use App\Http\Resources\Api\Ticket\TicketCommentResource;
use App\Http\Resources\Api\Ticket\TicketResource;
use App\Models\Ticket\Ticket;
use App\Services\Ticket\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        return TicketResource::collection($this->ticketService->paginate($request->validated(), $request->user()));
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated(), $request->user());

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        abort_unless($request->user()?->can('view', $ticket), 403);

        return new TicketResource($ticket->load(['category', 'team', 'assignee', 'comments.author', 'aiSuggestion']));
    }

    public function assume(Request $request, Ticket $ticket): TicketResource
    {
        abort_unless($request->user()?->can('assume', $ticket), 403);

        return new TicketResource($this->ticketService->assume($ticket, $request->user()));
    }

    public function updateStatus(ChangeTicketStatusRequest $request, Ticket $ticket): TicketResource
    {
        return new TicketResource($this->ticketService->changeStatus($ticket, $request->validated('status')));
    }

    public function comment(CreateTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $comment = $this->ticketService->addComment($ticket, $request->user(), $request->validated('body'));

        return (new TicketCommentResource($comment))->response()->setStatusCode(201);
    }
}
