<?php

namespace App\Http\Controllers;

use App\Models\InventoryEvent;
use App\Models\Item;
use App\Models\LoanDetail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryEvidenceController extends Controller
{
    public function loanReturn(LoanDetail $loanDetail): StreamedResponse
    {
        $loanDetail->load('loan');
        $user = auth()->user();
        abort_unless(
            $user->is_active && ($loanDetail->loan->user_id === $user->id || $user->isStaff()),
            403
        );

        return $this->download($loanDetail->return_photo);
    }

    public function inventory(Item $item, InventoryEvent $event): StreamedResponse
    {
        abort_unless($event->item_id === $item->id, 404);

        return $this->download($event->evidence_path);
    }

    private function download(?string $path): StreamedResponse
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
