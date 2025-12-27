<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactLead extends Model
{
    protected $table = 'contact_leads';

    protected $fillable = [
        'contact_name','contact_email','contact_phone',
        'central_name','city','state','message',
        'source','status','ip','user_agent',
    ];
}
