<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IssueHistory extends Model
{
    use HasFactory;

    
    protected $table = 'issue_history';
    
    protected $fillable = [
        'issue_id', 'changed_by', 'field', 'old_value', 'new_value', 'comment'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
