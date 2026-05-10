<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GateCard;
use App\Models\Guru;

class GateCardController extends Controller
{
    public function index()
    {
        $schoolId = auth()->user()->school_id;
        $gateCards = GateCard::where('school_id', $schoolId)->get();
        $gurus = Guru::where('school_id', $schoolId)->orderBy('nama')->get();
        return view('gate-cards.index', compact('gateCards', 'gurus'));
    }

    public function create()
    {
        $schoolId = auth()->user()->school_id;
        $gurus = Guru::where('school_id', $schoolId)->orderBy('nama')->get();
        return view('gate-cards.create', compact('gurus'));
    }

    public function store(Request $request)
    {
        if ($request->guru_id === 'lainnya') {
            $request->merge(['guru_id' => null]);
        }

        $request->validate([
            'guru_id' => 'nullable|exists:guru,id',
            'name' => 'required_without:guru_id|string|max:100|nullable',
            'uid_rfid' => 'nullable|string|max:50',
        ]);

        $name = $request->name;
        if ($request->filled('guru_id')) {
            $guru = Guru::find($request->guru_id);
            if ($guru && $guru->school_id === auth()->user()->school_id) {
                $name = $guru->nama;
            } else {
                abort(403, 'Invalid Guru ID');
            }
        }

        GateCard::create([
            'school_id' => auth()->user()->school_id,
            'guru_id' => $request->guru_id,
            'name' => $name,
            'uid_rfid' => $request->uid_rfid,
            'enroll_status' => $request->uid_rfid ? 'done' : 'requested'
        ]);

        return redirect()->route('gate-cards.index')->with('success', 'Kartu Gerbang berhasil ditambahkan.');
    }

    public function edit(GateCard $gateCard)
    {
        // Scope to school
        if ($gateCard->school_id !== auth()->user()->school_id) abort(403);
        
        $schoolId = auth()->user()->school_id;
        $gurus = Guru::where('school_id', $schoolId)->orderBy('nama')->get();
        return view('gate-cards.edit', compact('gateCard', 'gurus'));
    }

    public function update(Request $request, GateCard $gateCard)
    {
        if ($gateCard->school_id !== auth()->user()->school_id) abort(403);

        if ($request->guru_id === 'lainnya') {
            $request->merge(['guru_id' => null]);
        }

        $request->validate([
            'guru_id' => 'nullable|exists:guru,id',
            'name' => 'required_without:guru_id|string|max:100|nullable',
            'uid_rfid' => 'nullable|string|max:50',
        ]);

        $name = $request->name;
        if ($request->filled('guru_id')) {
            $guru = Guru::find($request->guru_id);
            if ($guru && $guru->school_id === auth()->user()->school_id) {
                $name = $guru->nama;
            } else {
                abort(403, 'Invalid Guru ID');
            }
        }

        $gateCard->update([
            'guru_id' => $request->guru_id,
            'name' => $name,
            'uid_rfid' => $request->uid_rfid,
        ]);

        return redirect()->route('gate-cards.index')->with('success', 'Kartu Gerbang berhasil diperbarui.');
    }

    public function destroy(GateCard $gateCard)
    {
        if ($gateCard->school_id !== auth()->user()->school_id) abort(403);
        
        $gateCard->delete();
        return redirect()->route('gate-cards.index')->with('success', 'Kartu Gerbang berhasil dihapus.');
    }

    public function requestEnroll(GateCard $gateCard)
    {
        if ($gateCard->school_id !== auth()->user()->school_id) abort(403);

        // Reset others to done
        GateCard::where('school_id', auth()->user()->school_id)
            ->where('enroll_status', 'requested')
            ->update(['enroll_status' => 'done']);

        $gateCard->update([
            'enroll_status' => 'requested'
        ]);

        return back()->with('success', 'Silakan tempelkan kartu pada alat absensi (RFID).');
    }

    public function enrollRequest($id)
    {
        $gateCard = GateCard::where('school_id', auth()->user()->school_id)->findOrFail($id);
        
        // Reset others
        GateCard::where('school_id', auth()->user()->school_id)
            ->where('enroll_status', 'requested')
            ->update(['enroll_status' => 'done']);

        $gateCard->update(['enroll_status' => 'requested']);
        return response()->json(['ok' => true]);
    }

    public function cancelEnroll($id)
    {
        $gateCard = GateCard::where('school_id', auth()->user()->school_id)->findOrFail($id);
        $gateCard->update(['enroll_status' => 'done']);
        return response()->json(['ok' => true]);
    }

    public function enrollCheck($id)
    {
        $gateCard = GateCard::where('school_id', auth()->user()->school_id)->findOrFail($id);
        if ($gateCard->enroll_status == 'done') {
            return response()->json(['ok' => true, 'uid' => $gateCard->uid_rfid]);
        } elseif (str_starts_with($gateCard->enroll_status, 'error:')) {
            $errorMsg = substr($gateCard->enroll_status, 6);
            $gateCard->update(['enroll_status' => 'none']);
            return response()->json(['ok' => false, 'error' => $errorMsg]);
        }
        return response()->json(['ok' => false]);
    }

    public function deleteUid($id)
    {
        $gateCard = GateCard::where('school_id', auth()->user()->school_id)->findOrFail($id);
        $gateCard->update([
            'uid_rfid' => null,
            'enroll_status' => 'done'
        ]);
        return response()->json(['ok' => true]);
    }
}

