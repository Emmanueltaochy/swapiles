<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function edit()
    {
        return view('account.addresses.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        auth()->user()->update($data);

        return back()->with('status', 'Adresse enregistrée.');
    }
}
