<?php
namespace Albertoesquitino\Ticketit\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Albertoesquitino\Ticketit\Models;
use Albertoesquitino\Ticketit\Models\Agent;
use Albertoesquitino\Ticketit\Models\Ticket;
use Albertoesquitino\Ticketit\Requests\PrepareTicketRequest;

class TicketsController extends Controller
{

    protected $tickets;
    protected $agent;

    public function __construct(Ticket $tickets, Agent $agent)
    {
        $this->middleware('Albertoesquitino\Ticketit\Middleware\ResAccessMiddleware', ['only' => ['show']]);
        $this->middleware('Albertoesquitino\Ticketit\Middleware\IsAgentMiddleware', ['only' => ['edit', 'update']]);
        $this->middleware('Albertoesquitino\Ticketit\Middleware\IsAdminMiddleware', ['only' => ['destroy']]);

        $this->tickets = $tickets;
        $this->agent = $agent;
    }

    /**
     * Display a listing of active tickets related to user.
     *
     * @return Response
     */
    public function index()
    {
        $items = config('ticketit.paginate_items');
        if ($this->agent->isAdmin()) {
            $tickets = $this->tickets->active()->orderBy('updated_at', 'desc')->paginate($items);
        } elseif ($this->agent->isAgent()) {
            $agent = $this->agent->find(auth()->user()->id);
            $tickets = $agent->agentTickets()->orderBy('updated_at', 'desc')->paginate($items);
        } else {
            $user = $this->agent->find(auth()->user()->id);
            $tickets = $user->userTickets()->orderBy('updated_at', 'desc')->paginate($items);
        }
        return view('ticketit::index', compact('tickets'));
    }

    /**
     * Display a listing of completed tickets related to user.
     *
     * @return Response
     */
    public function indexComplete()
    {
        $items = config('ticketit.paginate_items');
        if ($this->agent->isAdmin()) {
            $tickets = $this->tickets->complete()->orderBy('updated_at', 'desc')->paginate($items);
        } elseif ($this->agent->isAgent()) {
            $agent = $this->agent->find(auth()->user()->id);
            $tickets = $agent->agentTickets(true)->orderBy('updated_at', 'desc')->paginate($items);
        } else {
            $user = $this->agent->find(auth()->user()->id);
            $tickets = $user->userTickets(true)->orderBy('updated_at', 'desc')->paginate($items);
        }
        return view('ticketit::index', compact('tickets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $priorities = Models\Priority::lists('name', 'id');
        $categories = Models\Category::lists('name', 'id');
        return view('ticketit::tickets.create', compact('priorities', 'categories'));
    }

    /**
     * Store a newly created ticket and auto assign an agent for it
     *
     * @param  Request  $request
     * @return Response redirect to index
     */
    public function store(PrepareTicketRequest $request)
    {
        $ticket = new Ticket;

        $ticket->subject = $request->subject;
        $ticket->content = $request->content;
        $ticket->priority_id = $request->priority_id;
        $ticket->category_id = $request->category_id;

        $ticket->status_id = config('ticketit.default_status_id');
        $ticket->user_id = auth()->user()->id;
        $ticket->agent_id = $this->autoSelectAgent($request->input('category_id'));

        $ticket->save();

        session()->flash('status', trans('ticketit::lang.the-ticket-has-been-created'));

        return redirect()->action('\Albertoesquitino\Ticketit\Controllers\TicketsController@index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $ticket = $this->tickets->find($id);

        $status_lists = Models\Status::lists('name', 'id');
        $priority_lists = Models\Priority::lists('name', 'id');
        $category_lists = Models\Category::lists('name', 'id');

        $close_perm = $this->permToClose($id);
        $reopen_perm = $this->permToReopen($id);

        $cat_agents = Models\Category::find($ticket->category_id)->agents()->agentsLists();
        if (is_array($cat_agents)) {
            $agent_lists = ['auto' => 'Auto Select'] + $cat_agents;
        }
        else {
            $agent_lists = ['auto' => 'Auto Select'];
        }

        $comments = $ticket->comments()->paginate(config('ticketit.paginate_items'));
        return view('ticketit::tickets.show',
            compact('ticket', 'status_lists', 'priority_lists', 'category_lists', 'agent_lists', 'comments',
                'close_perm', 'reopen_perm'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(PrepareTicketRequest $request, $id)
    {
        $ticket = $this->tickets->findOrFail($id);

        $ticket->subject = $request->subject;
        $ticket->content = $request->content;
        $ticket->status_id = $request->status_id;
        $ticket->category_id = $request->category_id;
        $ticket->priority_id = $request->priority_id;

        if ($request->input('agent_id') == 'auto') {
            $ticket->agent_id = $this->autoSelectAgent($request->input('category_id'));
        } else {
            $ticket->agent_id = $request->input('agent_id');
        }

        $ticket->save();

        session()->flash('status', trans('ticketit::lang.the-ticket-has-been-modified'));        

        return redirect()->route(config('ticketit.main_route') . '.show', $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $ticket = $this->tickets->findOrFail($id);
        $subject = $ticket->subject;
        $ticket->delete();

        session()->flash('status', trans('ticketit::lang.the-ticket-has-been-deleted', ['name' => $subject])); 
        
        return redirect()->route(config('ticketit.main_route') . '.index');
    }

    /**
     * Mark ticket as complete.
     *
     * @param  int $id
     * @return Response
     */
    public function complete($id)
    {
        if ($this->permToClose($id) == 'yes') {

            $ticket = $this->tickets->findOrFail($id);
            $ticket->completed_at = Carbon::now();

            if(config('ticketit.default_close_status_id')) {
                $ticket->status_id = config('ticketit.default_close_status_id');
            }

            $subject = $ticket->subject;
            $ticket->save();

             session()->flash('status', trans('ticketit::lang.the-ticket-has-been-completed', ['name' => $subject]));             

            return redirect()->route(config('ticketit.main_route') . '.index');
        }

        return redirect()->route(config('ticketit.main_route') . '.index')
                            ->with('warning', 'You are not permitted to do this action!');
    }

    /**
     * Reopen ticket from complete status.
     *
     * @param  int $id
     * @return Response
     */
    public function reopen($id)
    {
        if ($this->permToReopen($id) == 'yes') {

            $ticket = $this->tickets->findOrFail($id);
            $ticket->completed_at = null;

            if(config('ticketit.default_reopen_status_id')) {
                $ticket->status_id = config('ticketit.default_reopen_status_id');
            }

            $subject = $ticket->subject;
            $ticket->save();

            session()->flash('status', trans('ticketit::lang.the-ticket-has-been-reopened', ['name' => $subject]));

            return redirect()->route(config('ticketit.main_route') . '.index');
        }

        return redirect()->route(config('ticketit.main_route') . '.index')
                            ->with('warning', 'You are not permitted to do this action!');
    }

    /**
     * Get the agent with the lowest tickets assigned in specific category
     * @param integer $cat_id
     * @return integer $selected_agent_id
     */
    public function autoSelectAgent($cat_id)
    {
        $agents = Models\Category::find($cat_id)->agents()->agents();
        $count = 0;
        $lowest_tickets = 1000000;

        // If no agent is selected, select admin
        $selected_agent_id = config('ticketit.admin_ids')[0];

        foreach ($agents as $agent) {
            if ($count == 0) {
                $lowest_tickets = $agent->agentTickets->count();
                $selected_agent_id = $agent->id;
            }
            else {
                $tickets_count = $agent->agentTickets->count();
                if ($tickets_count < $lowest_tickets) {
                    $lowest_tickets = $tickets_count;
                    $selected_agent_id = $agent->id;
                }
            }
            $count++;
        }
        return $selected_agent_id;
    }

    public function agentSelectList($category_id, $ticket_id)
    {
        $cat_agents = Models\Category::find($category_id)->agents()->agentsLists();
        if (is_array($cat_agents)) {
            $agents = ['auto' => 'Auto Select'] + $cat_agents;
        }
        else {
            $agents = ['auto' => 'Auto Select'];
        }

        $selected_Agent = $this->tickets->find($ticket_id)->agent->id;
        $select = '<select class="form-control" id="agent_id" name="agent_id">';
        foreach ($agents as $id => $name) {
            $selected = ($id == $selected_Agent) ? "selected" : "";
            $select .= '<option value="' . $id . '" ' . $selected . '>' . $name . '</option>';
        }
        $select .= '</select>';
        return $select;
    }

    /**
     * @param $id
     * @return bool
     */
    public function permToClose($id)
    {
        $close_ticket_perm = config('ticketit.close_ticket_perm');

        if ($this->agent->isAdmin() && $close_ticket_perm['admin'] == 'yes') {
            return 'yes';
        }
        if ($this->agent->isAgent() && $close_ticket_perm['agent'] == 'yes') {
            return 'yes';
        }
        if ($this->agent->isTicketOwner($id) && $close_ticket_perm['owner'] == 'yes') {
            return 'yes';
        }
        return 'no';
    }

    /**
     * @param $id
     * @return bool
     */
    public function permToReopen($id)
    {
        $reopen_ticket_perm = config('ticketit.reopen_ticket_perm');
        if ($this->agent->isAdmin() && $reopen_ticket_perm['admin'] == 'yes') {
            return 'yes';
        } elseif ($this->agent->isAgent() && $reopen_ticket_perm['agent'] == 'yes') {
            return 'yes';
        } elseif ($this->agent->isTicketOwner($id) && $reopen_ticket_perm['owner'] == 'yes') {
            return 'yes';
        }
        return 'no';
    }
}

