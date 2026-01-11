<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideIssueNote extends Model
{
    protected $fillable = ['tenant_id','ride_issue_id','user_id','visibility','note'];

    public function issue() { return $this->belongsTo(RideIssue::class, 'ride_issue_id'); }
    public function user() { return $this->belongsTo(User::class); }
}