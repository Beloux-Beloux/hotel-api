<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Issue extends Model
{
    use HasFactory;


    protected $fillable = [
        'title', 'description', 'room', 'urgency', 'status', 'reported_by', 'assigned_to'
    ];

    public function reporter() {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
