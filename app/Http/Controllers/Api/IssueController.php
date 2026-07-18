<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\Request;
use App\Models\IssueHistory;

class IssueController extends Controller
{
    // Liste des problèmes
    public function index() {
        return Issue::with('reporter')->get();
    }

    // Détail d'un problème
    public function show($id) {
        return Issue::with('reporter', 'assignee')->findOrFail($id);
    }


    // Assigner un problème
    public function assign(Request $request, $id) {
        $issue = Issue::findOrFail($id);
        $issue->assigned_to = $request->assigned_to;
        $issue->status = 'assigned';
        $issue->save();
        return response()->json($issue);
    }


    // Lors d’une mise à jour ou assignation
    public function update(Request $request, $id) {
        $issue = Issue::findOrFail($id);
        
        foreach (['status','description','assigned_to'] as $field) {
            if ($request->has($field) && $issue->$field != $request->$field) {
                IssueHistory::create([
                    'issue_id' => $issue->id,
                    'changed_by' => auth()->id(),
                    'field' => $field,
                    'old_value' => (string)$issue->$field,
                    'new_value' => (string)$request->$field,
                    'comment' => $request->comment ?? null,
                ]);
            }
        }

        $issue->update($request->only(['status','description','assigned_to']));
        return response()->json($issue);
    }

    // Endpoint pour récupérer l’historique d’un problème
    public function history($id) {
        $histories = IssueHistory::with('user')->where('issue_id', $id)->orderBy('created_at','desc')->get();
        
        return response()->json($histories);
    }

    // Assignation avec commentaire
    public function assignHistory(Request $request, $id) {
        $issue = Issue::findOrFail($id);

        // Historique
        IssueHistory::create([
            'issue_id' => $issue->id,
            'changed_by' => auth()->id(),
            'field' => 'assigned_to',
            'old_value' => (string)$issue->assigned_to,
            'new_value' => (string)$request->assigned_to,
            'comment' => $request->comment ?? null,
        ]);

        $issue->assigned_to = $request->assigned_to;
        $issue->status = 'assigned';
        $issue->save();

        return response()->json($issue);
    }

    // IssueController.php
    public function stats() {
        $issues = Issue::all();
        $stats = [
            'total' => $issues->count(),
            'pending' => $issues->where('status','pending')->count(),
            'in_progress' => $issues->where('status','in_progress')->count(),
            'resolved' => $issues->where('status','resolved')->count(),
            'urgent' => $issues->where('urgency','high')->count(),
            // Problèmes récurrents par chambre et titre
            'recurring' => $issues->groupBy(['room','title'])->filter(fn($group)=> $group->count() > 1)->count(),
        ];
        return response()->json($stats);
    }


}
