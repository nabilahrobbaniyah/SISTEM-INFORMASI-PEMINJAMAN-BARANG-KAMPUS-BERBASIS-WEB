<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\View\View;

class LoanPrintController extends Controller
{
    /** Bukti peminjaman siap cetak (gunakan "Simpan sebagai PDF" di dialog cetak browser). */
    public function show(Loan $loan): View
    {
        $user = auth()->user();

        abort_unless($user->is_active && ($user->isStaff() || (int) $loan->user_id === (int) $user->id), 403);
        abort_if(in_array($loan->status, ['menunggu', 'ditolak', 'dibatalkan'], true), 404, 'Bukti hanya tersedia untuk peminjaman yang sudah disetujui.');

        $loan->load(['details.item', 'details.returnInspector', 'user', 'processor', 'handedOverBy', 'returnedTo']);

        return view('loans.print', compact('loan'));
    }
}
