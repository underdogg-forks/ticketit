<?php

namespace Albertoesquitino\Ticketit\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model {

    protected $table = 'ticketit_comments';

    /**
     * Get related ticket
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticket() {
        return $this->belongsTo('Albertoesquitino\Ticketit\Models\Ticket', 'ticket_id');
    }

    /**
     * Get comment owner
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}
